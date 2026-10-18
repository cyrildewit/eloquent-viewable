<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Presence\Stores;

use CyrildeWit\EloquentViewable\Presence\Contracts\PresenceStore;
use CyrildeWit\EloquentViewable\Presence\Exceptions\PresenceFailed;
use CyrildeWit\EloquentViewable\Presence\Precision;
use CyrildeWit\EloquentViewable\Presence\PresenceConfig;
use CyrildeWit\EloquentViewable\Support\PackageManager;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Contracts\Redis\Factory as RedisFactory;
use Illuminate\Redis\Connections\PhpRedisConnection;
use Illuminate\Redis\Connections\PredisConnection;

/** @extends PackageManager<PresenceStore, PresenceConfig> */
final class PresenceManager extends PackageManager
{
    public function getDefaultDriver(): string
    {
        if (! $this->packageConfig->enabled()) {
            return 'null';
        }

        return $this->packageConfig->driver();
    }

    protected function configKey(): string
    {
        return 'presence.driver';
    }

    protected function createNullDriver(): NullPresenceStore
    {
        return new NullPresenceStore;
    }

    protected function createArrayDriver(): ArrayPresenceStore
    {
        return new ArrayPresenceStore;
    }

    /** @throws PresenceFailed */
    protected function createRedisDriver(): RedisPresenceStore
    {
        $connection = $this->container->make(RedisFactory::class)->connection($this->packageConfig->redisConnection());

        if (! $connection instanceof PhpRedisConnection && ! $connection instanceof PredisConnection) {
            throw PresenceFailed::unsupportedRedisClient($connection::class);
        }

        return new RedisPresenceStore(
            $connection,
            $this->packageConfig->redisPrefix(),
            $this->packageConfig->window(),
            Precision::from($this->packageConfig->precision()),
            $this->container->make(ExceptionHandler::class),
        );
    }
}
