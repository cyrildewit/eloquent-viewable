<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Presence\Stores;

use CyrildeWit\EloquentViewable\Presence\Contracts\PresenceStore;
use CyrildeWit\EloquentViewable\Presence\Exceptions\PresenceFailed;
use CyrildeWit\EloquentViewable\Presence\Precision;
use CyrildeWit\EloquentViewable\Support\PackageManager;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Contracts\Redis\Factory as RedisFactory;
use Illuminate\Redis\Connections\PhpRedisConnection;
use Illuminate\Redis\Connections\PredisConnection;

/** @extends PackageManager<PresenceStore> */
final class PresenceManager extends PackageManager
{
    public function getDefaultDriver(): string
    {
        if (! $this->packageConfig->presenceEnabled()) {
            return 'null';
        }

        return $this->packageConfig->presenceDriver();
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
        $connection = $this->container->make(RedisFactory::class)->connection($this->packageConfig->presenceRedisConnection());

        if (! $connection instanceof PhpRedisConnection && ! $connection instanceof PredisConnection) {
            throw PresenceFailed::unsupportedRedisClient($connection::class);
        }

        return new RedisPresenceStore(
            $connection,
            $this->packageConfig->presenceRedisPrefix(),
            $this->packageConfig->presenceWindow(),
            Precision::from($this->packageConfig->presencePrecision()),
            $this->container->make(ExceptionHandler::class),
        );
    }
}
