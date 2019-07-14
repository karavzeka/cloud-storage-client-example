<?php
declare(strict_types=1);

namespace Kluatr\ClientSelectelStorage;

use Kluatr\Exception\Runtime\Logic\NotExists\KeyIsNotExistsException;
use Kluatr\Framework\Foundation\BaseComponent;
use Kluatr\Redis\RedisCache;

class SelectelClientFactory extends BaseComponent
{
    // Virtual types of containers for getting an access to particular type of content. Depending on the user, they can leads to different containers.
    const STORAGE_TYPE_STATIC = 'static';
    const STORAGE_TYPE_VIDEO = 'video';

    // Names of our containers in the storage
    const CONTAINER_1 = 'container_1';
    const CONTAINER_2 = 'container_2';
    const CONTAINER_3 = 'container_3';

    /**
     * @var RedisCache Component for working with redis
     */
    private $redisCache;

    /**
     * @var array List of settings to resolve access to containers
     */
    private $containerCredentials = [];

    /**
     * @var array Stack of clients
     */
    private static $clients = [];

    public function __construct(RedisCache $redisCache)
    {
        parent::__construct();

        $this->redisCache = $redisCache;
    }

    /**
     * @return string
     */
    public function getConfigName(): string
    {
        return 'selectelStorageApi';
    }

    public function configureParameters(array $config): void
    {
        foreach ($config as $storageType => $storageProperties) {
            $this->containerCredentials[$storageType] = [
                'container' => $storageProperties['container'],
                'publicHost' => $storageProperties['publicHost'],
                'login' => $storageProperties['login'],
                'password' => $storageProperties['password'],
            ];
        }
    }

    /**
     * Returns a client for working with static container
     *
     * @return Client
     * @throws KeyIsNotExistsException
     */
    public function getStaticStorageClient(): Client
    {
        if (!isset(self::$clients[self::STORAGE_TYPE_STATIC])) {
            if (!isset($this->containerCredentials[self::STORAGE_TYPE_STATIC])) {
                throw new KeyIsNotExistsException('There isn\'t connection settings for storage \'' . self::STORAGE_TYPE_STATIC . '\'');
            }

            $user = new User(
                $this->containerCredentials[self::STORAGE_TYPE_STATIC]['login'],
                $this->containerCredentials[self::STORAGE_TYPE_STATIC]['password'],
                $this->redisCache
            );
            self::$clients[self::STORAGE_TYPE_STATIC] = new Client(
                $this->containerCredentials[self::STORAGE_TYPE_STATIC]['container'],
                $user,
                $this->containerCredentials[self::STORAGE_TYPE_STATIC]['publicHost']
            );
        }

        return self::$clients[self::STORAGE_TYPE_STATIC];
    }

    /**
     * Returns a client for working with video container
     *
     * @return Client
     * @throws KeyIsNotExistsException
     */
    public function getVideoStorageClient(): Client
    {
        if (!isset(self::$clients[self::STORAGE_TYPE_VIDEO])) {
            if (!isset($this->containerCredentials[self::STORAGE_TYPE_VIDEO])) {
                throw new KeyIsNotExistsException('There isn\'t connection settings for storage \'' . self::STORAGE_TYPE_VIDEO . '\'');
            }

            $user = new User(
                $this->containerCredentials[self::STORAGE_TYPE_VIDEO]['login'],
                $this->containerCredentials[self::STORAGE_TYPE_VIDEO]['password'],
                $this->redisCache
            );
            self::$clients[self::STORAGE_TYPE_VIDEO] = new Client(
                $this->containerCredentials[self::STORAGE_TYPE_VIDEO]['container'],
                $user,
                $this->containerCredentials[self::STORAGE_TYPE_VIDEO]['publicHost']
            );
        }

        return self::$clients[self::STORAGE_TYPE_VIDEO];
    }
}