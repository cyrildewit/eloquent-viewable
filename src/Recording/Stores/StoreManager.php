<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Recording\Stores;

use CyrildeWit\EloquentViewable\Exceptions\InvalidConfiguration;
use CyrildeWit\EloquentViewable\Recording\Contracts\ViewStore;
use CyrildeWit\EloquentViewable\Recording\Exceptions\UnsupportedRedisClient;
use CyrildeWit\EloquentViewable\Recording\Streams\Clients\ClientFactory;
use CyrildeWit\EloquentViewable\Recording\Streams\ViewStream;
use CyrildeWit\EloquentViewable\Support\Config;
use Illuminate\Contracts\Container\Container;
use Illuminate\Contracts\Redis\Factory as RedisFactory;
use Illuminate\Support\Manager;
use Illuminate\Support\Str;

/** @method ViewStore driver(?string $driver = null) */
final class StoreManager extends Manager
{
    public function __construct(Container $container, private readonly Config $packageConfig)
    {
        parent::__construct($container);
    }

    public function getDefaultDriver(): string
    {
        return $this->packageConfig->storeDriver();
    }

    /**
     * @param  string  $driver
     *
     * @throws InvalidConfiguration
     */
    #[\Override]
    protected function createDriver($driver): ViewStore
    {
        if (! isset($this->customCreators[$driver]) && ! method_exists($this, 'create'.Str::studly($driver).'Driver')) {
            throw InvalidConfiguration::unknownDriver('recording.store.driver', $driver);
        }

        /** @var ViewStore $store */
        $store = parent::createDriver($driver);

        return $store;
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
