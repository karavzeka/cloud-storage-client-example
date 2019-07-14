<?php

declare(strict_types=1);

namespace Kluatr\ClientSelectelStorage;

use Kluatr\Exception\Runtime\Logic\InvalidValue\UnexpectedValueException;
use Kluatr\Exception\RuntimeException;
use Kluatr\Exception\Error\FileSystem\FileNotExistError;
use Kluatr\ClientSelectelStorage\Http\CurlExecutor;
use Kluatr\Utils\NumericHelper;

/**
 * The class which do requests to API of cloud storage
 */
class Client
{
    /**
     * Url for authorization
     */
    const AUTHORIZATION_URL = '<auth_url>';

    /**
     * URL for all requests except for authorization
     */
    const REQUEST_URL = '<request_url>';

    /**
     * @var User The user who is accessing the container
     */
    private $selectelUser;

    /**
     * @var string Container path. Files are stored relative to it.
     */
    private $containerPath = '';

    /**
     * @var string Public host. It is used by users to access files.
     */
    private $publicHost = '';

    public function __construct(string $containerName, User $user, string $publicHost)
    {
        $this->selectelUser = $user;

        // Add slash to the beginning
        if (\substr($containerName, 0, 1) !== '/') {
            $containerName = '/' . $containerName;
        }
        // Remove slash at the ending
        if (\substr($containerName, -1) === '/') {
            $containerName = \substr($containerName, 0, -1);
        }
        $this->containerPath = $containerName;

        $this->publicHost = \rtrim($publicHost, '/');
    }

    /**
     * Returns the list of all files in the container.
     *
     * @return array
     * @throws RuntimeException
     * @throws UnexpectedValueException
     */
    public function getFullFileList(): array
    {
        $token = $this->selectelUser->getToken();

        $curlOptions = [
            \CURLOPT_URL => Client::REQUEST_URL . $this->containerPath,
            \CURLOPT_RETURNTRANSFER => true,
            \CURLOPT_HEADER => true,
            \CURLINFO_HEADER_OUT => true,
            \CURLOPT_HTTPHEADER => [
                'X-Auth-Token: ' . $token,
            ],
        ];
        $response = CurlExecutor::exec($curlOptions);

        if ($response->getStatusCode() === 401) {
            $token = $this->selectelUser->refreshToken();

            $curlOptions[\CURLOPT_HTTPHEADER][0] = 'X-Auth-Token: ' . $token;
            $response = CurlExecutor::exec($curlOptions);

            if (!NumericHelper::inRange($response->getStatusCode(), 200, 300, NumericHelper::RANGE_INCLUDE_LOW)) {
                throw new RuntimeException('Service responds code ' . $response->getStatusCode() . ". Request:\n\n" . CurlExecutor::getLastRequestHeaders());
            }
        }

        $fileList = [];
        $body = $response->getBody();
        if (!empty($body)) {
            $fileList = \explode("\n", $body);
        }

        return $fileList;
    }

    /**
     * Downloads file from the storage.
     * Returns true if the size of downloaded file and file in the storage are the same.
     *
     * @param string $url
     * @param string $localPath
     * @return bool
     * @throws RuntimeException
     * @throws UnexpectedValueException
     */
    public function downloadFile(string $url, string $localPath): bool
    {
        $urlParts = \parse_url($url);
        $storagePath = $urlParts['path'];

        // Сначала проверяем наличие файла
        $curlOptions = [
            \CURLOPT_URL => Client::REQUEST_URL . $this->containerPath . $storagePath,
            \CURLOPT_RETURNTRANSFER => true,
            \CURLOPT_HEADER => true,
            \CURLINFO_HEADER_OUT => true,
            \CURLOPT_NOBODY => true
        ];
        $response = CurlExecutor::exec($curlOptions);

        if ($response->getStatusCode() === 404) {
            throw new RuntimeException('File ' . $storagePath . ' is not found');
        } elseif (!NumericHelper::inRange($response->getStatusCode(), 200, 300, NumericHelper::RANGE_INCLUDE_LOW)) {
            throw new RuntimeException('Service responds code ' . $response->getStatusCode() . ". Request:\n\n" . CurlExecutor::getLastRequestHeaders());
        }

        // Query for downloading (without copying to the memory)
        $fh = \fopen($localPath, 'w+');

        $curlOptions = [
            \CURLOPT_URL => Client::REQUEST_URL . $this->containerPath . $storagePath,
            \CURLOPT_TIMEOUT => 30,
            \CURLOPT_FOLLOWLOCATION => true,
            \CURLOPT_MAXREDIRS => 4,
            \CURLOPT_FILE => $fh,
        ];

        $ch = \curl_init();
        \curl_setopt_array($ch, $curlOptions);
        \curl_exec($ch);
        \curl_close($ch);
        \fclose($fh);

        return \filesize($localPath) === (int) $response->getHeader('Content-Length');
    }

    /**
     * Uploads local file to the storage
     *
     * @param string $localPath
     * @param string $storagePath
     * @return int HTTP code
     * @throws FileNotExistError
     * @throws RuntimeException
     * @throws UnexpectedValueException
     */
    public function uploadFile(string $localPath, string $storagePath): int
    {
        if (!\is_file($localPath)) {
            throw new FileNotExistError($localPath . ' is not exist');
        }
        if (\substr($storagePath, 0, 1) !== '/') {
            // Add slash to the beginning if it is not exists
            $storagePath = '/' . $storagePath;
        }

        $token = $this->selectelUser->getToken();

        $uploadUrl = Client::REQUEST_URL . $this->containerPath . $storagePath;
        $fileSize = \filesize($localPath);
        $curlOptions = [
            \CURLOPT_URL => $uploadUrl,
            \CURLOPT_UPLOAD => true,
            \CURLOPT_INFILESIZE => $fileSize,
            \CURLOPT_INFILE => \fopen($localPath, 'r'),
            \CURLOPT_RETURNTRANSFER => true,
            \CURLOPT_HEADER => true,
            \CURLINFO_HEADER_OUT => true,
            \CURLOPT_HTTPHEADER => [
                'X-Auth-Token: ' . $token,
            ],
        ];
        $response = CurlExecutor::exec($curlOptions);

        if ($response->getStatusCode() === 401) {
            $token = $this->selectelUser->refreshToken();

            $curlOptions[\CURLOPT_HTTPHEADER][0] = 'X-Auth-Token: ' . $token;
            $response = CurlExecutor::exec($curlOptions);

            if (!NumericHelper::inRange($response->getStatusCode(), 200, 300, NumericHelper::RANGE_INCLUDE_LOW)) {
                throw new RuntimeException('Service responds code ' . $response->getStatusCode() . ". Request:\n\n" . CurlExecutor::getLastRequestHeaders());
            }
        }

        return $response->getStatusCode();
    }

    /**
     * Makes the link in the storage.
     * Notice that it is possible to make a link to non-existent file, exception in that case won't be raised.
     *
     * @param string $originStoragePath
     * @param string $link
     * @return int HTTP code
     * @throws RuntimeException
     * @throws UnexpectedValueException
     */
    public function makeLink(string $originStoragePath, string $link): int
    {
        if (\substr($originStoragePath, 0, 1) !== '/') {
            // Add slash to the beginning if it is not exists
            $originStoragePath = '/' . $originStoragePath;
        }
        if (\substr($link, 0, 1) !== '/') {
            // Add slash to the beginning if it is not exists
            $link = '/' . $link;
        }

        $token = $this->selectelUser->getToken();

        $curlOptions = [
            \CURLOPT_URL => Client::REQUEST_URL . $this->containerPath . $link,
            \CURLOPT_RETURNTRANSFER => true,
            \CURLOPT_HEADER => true,
            \CURLINFO_HEADER_OUT => true,
            \CURLOPT_CUSTOMREQUEST => 'PUT',
            \CURLOPT_HTTPHEADER => [
                'X-Auth-Token: ' . $token,
                'Content-Type: x-storage/symlink',
                'X-Object-Meta-Location: ' . $this->containerPath . $originStoragePath,
                'Content-Length: 0',
            ],
        ];
        $response = CurlExecutor::exec($curlOptions);

        if ($response->getStatusCode() === 401) {
            $token = $this->selectelUser->refreshToken();

            $curlOptions[\CURLOPT_HTTPHEADER][0] = 'X-Auth-Token: ' . $token;
            $response = CurlExecutor::exec($curlOptions);

            if (!NumericHelper::inRange($response->getStatusCode(), 200, 300, NumericHelper::RANGE_INCLUDE_LOW)) {
                throw new RuntimeException('Service responds code ' . $response->getStatusCode() . ". Request:\n\n" . CurlExecutor::getLastRequestHeaders());
            }
        }

        return $response->getStatusCode();
    }

    /**
     * Deletes the file from the storage
     *
     * @param string $storagePath
     * @return int HTTP code
     * @throws RuntimeException
     * @throws UnexpectedValueException
     */
    public function deleteFile(string $storagePath): int
    {
        if (\substr($storagePath, 0, 1) !== '/') {
            // Add slash to the beginning if it is not exists
            $storagePath = '/' . $storagePath;
        }

        $token = $this->selectelUser->getToken();

        $curlOptions = [
            \CURLOPT_URL => Client::REQUEST_URL . $this->containerPath . $storagePath,
            \CURLOPT_RETURNTRANSFER => true,
            \CURLOPT_HEADER => true,
            \CURLINFO_HEADER_OUT => true,
            \CURLOPT_CUSTOMREQUEST => 'DELETE',
            \CURLOPT_HTTPHEADER => [
                'X-Auth-Token: ' . $token,
            ],
        ];
        $response = CurlExecutor::exec($curlOptions);

        if ($response->getStatusCode() === 401) {
            $token = $this->selectelUser->refreshToken();

            $curlOptions[\CURLOPT_HTTPHEADER][0] = 'X-Auth-Token: ' . $token;
            $response = CurlExecutor::exec($curlOptions);

            if (!NumericHelper::inRange($response->getStatusCode(), 200, 300, NumericHelper::RANGE_INCLUDE_LOW)) {
                throw new RuntimeException('Service responds code ' . $response->getStatusCode() . ". Request:\n\n" . CurlExecutor::getLastRequestHeaders());
            }
        }

        return $response->getStatusCode();
    }

    /**
     * Gets the path to file relative to the container and returns public link to this file.
     *
     * @param string $storagePath
     * @return string
     */
    public function getPublicUrl(string $storagePath): string
    {
        if (\substr($storagePath, 0, 1) !== '/') {
            // Add slash to the beginning if it is not exists
            $storagePath = '/' . $storagePath;
        }

        return '//' . $this->publicHost . $storagePath;
    }

    /**
     * Returns headers of the last request
     *
     * @return string
     */
    public function getLastRequestHeaders(): string
    {
        return CurlExecutor::getLastRequestHeaders();
    }

    /**
     * Returns headers of the last response
     *
     * @return string
     */
    public function getLastResponseHeaders(): string
    {
        return CurlExecutor::getLastResponseHeaders();
    }
}