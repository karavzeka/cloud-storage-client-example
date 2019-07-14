<?php
declare(strict_types=1);

namespace Kluatr\ClientSelectelStorage\Http;

/**
 * Class for doing curl requests.
 * Because of response can contain several status headers, class returns the last one:
 *
 * HTTP/1.1 100 Continue
 *
 * HTTP/1.1 201 Created
 * Access-Control-Allow-Origin: *
 * Access-Control-Expose-Headers: X-Backend-Timestamp, Etag, Last-Modified, X-Object-Manifest, X-Timestamp
 * Content-Length: 0
 * Content-Type: text/html
 * Etag: 8d970608688e5364c2949e514bbcbbcd
 * Date: Thu, 11 Oct 2018 10:49:32 GMT
 */
class CurlExecutor
{
    /**
     * @var string Last request headers
     */
    private static $lastRequestHeaders = '';

    /**
     * @var string Last response headers
     */
    private static $lastResponseHeaders = '';

    /**
     * Does HTTP request via curl
     * Waits array of options for function 'curl_setopt_array()' as an input parameter.
     *
     * @param array $options
     * @return Response
     */
    public static function exec(array $options): Response
    {
        $ch = \curl_init();
        \curl_setopt_array($ch, $options);
        $result = \curl_exec($ch);
        $info = \curl_getinfo($ch);
        self::$lastRequestHeaders = $info['request_header'];
        \curl_close($ch);

        $response = new Response();

        $responseParts = \explode("\r\n\r\n", $result);

        self::$lastResponseHeaders = \implode("\r\n\r\n", $responseParts);

        $bodyIndex = 0;
        foreach ($responseParts as $index => $part) {
            if (\substr($part, 0, 8) === 'HTTP/1.1') {
                // Headers
                $headerLines = \explode("\r\n", $part);

                // First line is status
                $firstLineParts = \explode(' ', \array_shift($headerLines));
                $response->setStatusCode((int) $firstLineParts[1]);

                // Rest rows of headers
                foreach ($headerLines as $headerLine) {
                    list($key, $value) = explode(': ', $headerLine);
                    $response->setHeader($key, $value);
                }
            } else {
                // First row doesn't contain HTTP/1.1, it means the body has begin
                $bodyIndex = $index;
                break;
            }
        }

        if ($bodyIndex > 0) {
            // If the index exists, compile body to string
            $response->setBody(\implode("\r\n\r\n", \array_slice($responseParts, $bodyIndex)));
        }

        return $response;
    }

    /**
     * Returns headers of the last request
     *
     * @return string
     */
    public static function getLastRequestHeaders(): string
    {
        return self::$lastRequestHeaders;
    }

    /**
     * Returns headers of the last response
     *
     * @return string
     */
    public static function getLastResponseHeaders(): string
    {
        return self::$lastResponseHeaders;
    }
}