<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Recording\Stores;

use CyrildeWit\EloquentViewable\Exceptions\InvalidConfiguration;
use CyrildeWit\EloquentViewable\Recording\Contracts\ViewStore;
use CyrildeWit\EloquentViewable\Recording\Exceptions\UnsupportedRedisClient;
use CyrildeWit\EloquentViewable\Recording\Streams\Clients\ClientFactory;
use CyrildeWit\EloquentViewable\Recording\Streams\ViewStream;
use CyrildeWit\EloquentViewable\Support\Config;
use CyrildeWit\EloquentViewable\Support\PackageManager;
use Illuminate\Contracts\Redis\Factory as RedisFactory;

/** @extends PackageManager<ViewStore, Config> */
final class StoreManager extends PackageManager
{
    public function getDefaultDriver(): string
    {
        return $this->packageConfig->storeDriver();
    }

    protected function configKey(): string
    {
        return 'recording.store.driver';
    }

    protected function createDatabaseDriver(): DatabaseStore
    {
        return $this->container->make(DatabaseStore::class);
    }

    protected function createNullDriver(): NullStore
    {
        return new NullStore;
    }

    protected function createArrayDriver(): ArrayStore
    {
        return new ArrayStore;
    }

    /**
     * @throws InvalidConfiguration
     * @throws UnsupportedRedisClient
     */
    protected function createRedisDriver(): RedisStreamStore
    {
        $landing = $this->packageConfig->redisLandingDriver();

        if ($landing === 'redis') {
            throw InvalidConfiguration::mustNameAnotherDriver('recording.store.redis.landing', 'redis');
        }

        $connection = $this->container->make(RedisFactory::class)->connection($this->packageConfig->redisConnection());

        return new RedisStreamStore(
            new ViewStream(ClientFactory::make($connection), $this->packageConfig->redisStream(), $this->packageConfig->redisGroup()),
            $this->driver($landing),
        );
    }
}
