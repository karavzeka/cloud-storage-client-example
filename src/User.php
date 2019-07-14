<?php
declare(strict_types=1);

namespace Kluatr\ClientSelectelStorage;

use Kluatr\Exception\RuntimeException;
use Kluatr\ClientSelectelStorage\Http\CurlExecutor;
use Kluatr\Redis\RedisCache;
use Kluatr\Utils\NumericHelper;

/**
 * Class of user for storage.
 * It can  do authorization request and keep token to redis.
 */
class User
{
    /**
     * Header name which contains token in response
     */
    const TOKEN_HEADER = 'X-Storage-Token';

    /**
     * TTL for keeping data in redis
     */
    const TTL = 21600; // 6 hours

    /**
     * @var RedisCache Component for working with redis
     */
    private $redisCache;

    /**
     * @var string Login for getting a token
     */
    private $login = '';

    /**
     * @var string Password for getting a token
     */
    private $password = '';

    /**
     * @var string Token for working with API
     */
    private $token = '';

    public function __construct(string $login, string $password, RedisCache $redisCache)
    {
        $this->login = $login;
        $this->password = $password;
        $this->redisCache = $redisCache;
    }

    /**
     * Returns token for current user
     *
     * @return string
     * @throws RuntimeException
     */
    public function getToken(): string
    {
        if (empty($this->token)) {
            $key = $this->getRedisKey();

            $token = $this->redisCache->get($key);
            if (empty($token)) {
                $token = self::authorize();
                // Put token to redis
                $this->redisCache->set($key, $token, self::TTL);
            }

            $this->token = $token;
        }

        return $this->token;
    }

    /**
     * Updates token
     *
     * @return string
     * @throws RuntimeException
     */
    public function refreshToken(): string
    {
        $token = $this->authorize();

        // Положим токен в редис
        $key = $this->getRedisKey();
        $this->redisCache->set($key, $token, self::TTL);

        $this->token = $token;
        return $this->token;
    }

    /**
     * Returns key for storing token in redis
     *
     * @return string
     */
    private function getRedisKey(): string
    {
        return $key = 'selectel_storage_token_' . $this->login;
    }

    /**
     * Makes authorization request and return token for future requests
     *
     * @return string
     * @throws RuntimeException
     */
    private function authorize(): string
    {
        $curlOptions = [
            \CURLOPT_URL => Client::AUTHORIZATION_URL . '/auth/v1.0',
            \CURLOPT_RETURNTRANSFER => true,
            \CURLOPT_HEADER => true,
            \CURLINFO_HEADER_OUT => true,
            \CURLOPT_HTTPHEADER => [
                'X-Auth-User: ' . $this->login,
                'X-Auth-Key: ' . $this->password,
            ],
        ];

        $response = CurlExecutor::exec($curlOptions);

        if (!NumericHelper::inRange($response->getStatusCode(), 200, 300, NumericHelper::RANGE_INCLUDE_LOW)) {
            throw new RuntimeException('Service responds ' . $response->getStatusCode() . " code.\nRequest:\n\n" . CurlExecutor::getLastRequestHeaders());
        }

        if (!$response->hasHeader(self::TOKEN_HEADER)) {
            throw new RuntimeException('Selectel doesn\'t return token upon authorization request');
        }

        return $response->getHeader(self::TOKEN_HEADER);
    }
}