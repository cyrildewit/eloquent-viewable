<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Presence\Stores;

use CyrildeWit\EloquentViewable\Exceptions\InvalidConfiguration;
use CyrildeWit\EloquentViewable\Presence\Contracts\PresenceStore;
use CyrildeWit\EloquentViewable\Presence\Exceptions\PresenceFailed;
use CyrildeWit\EloquentViewable\Presence\Precision;
use CyrildeWit\EloquentViewable\Support\Config;
use Illuminate\Contracts\Container\Container;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Contracts\Redis\Factory as RedisFactory;
use Illuminate\Redis\Connections\PhpRedisConnection;
use Illuminate\Redis\Connections\PredisConnection;
use Illuminate\Support\Manager;
use Illuminate\Support\Str;

/**
 * Hands out the `null` store while presence is off, so recording a view
 * costs nothing extra until it is turned on.
 *
 * @method PresenceStore driver(?string $driver = null)
 */
final class PresenceManager extends Manager
{
    public function __construct(Container $container, private readonly Config $packageConfig)
    {
        parent::__construct($container);
    }

    /** @throws InvalidConfiguration */
    public function getDefaultDriver(): string
    {
        if (! $this->packageConfig->presenceEnabled()) {
            return 'null';
        }

        return $this->packageConfig->presenceDriver();
    }

    /**
     * @param  string  $driver
     *
     * @throws InvalidConfiguration
     */
    #[\Override]
    protected function createDriver($driver): PresenceStore
    {
        if (! isset($this->customCreators[$driver]) && ! method_exists($this, 'create'.Str::studly($driver).'Driver')) {
            throw InvalidConfiguration::unknownDriver('presence.driver', $driver);
        }

        /** @var PresenceStore $store */
        $store = parent::createDriver($driver);

        return $store;
    }

    protected function createNullDriver(): NullPresenceStore
    {
        return new NullPresenceStore;
    }

    protected function createArrayDriver(): ArrayPresenceStore
    {
        return new ArrayPresenceStore;
    }

    /**
     * @throws InvalidConfiguration
     * @throws PresenceFailed
     */
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
