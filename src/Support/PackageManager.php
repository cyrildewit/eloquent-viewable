<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Support;

use CyrildeWit\EloquentViewable\Exceptions\InvalidConfiguration;
use Illuminate\Contracts\Container\Container;
use Illuminate\Support\Manager;
use Illuminate\Support\Str;

/**
 * A driver manager that reads its default from the package config and names
 * the config key when a driver is not registered, rather than Laravel's
 * "Driver [x] not supported".
 *
 * @template TDriver of object
 *
 * @internal
 */
abstract class PackageManager extends Manager
{
    public function __construct(Container $container, protected readonly Config $packageConfig)
    {
        parent::__construct($container);
    }

    /** @return TDriver */
    #[\Override]
    public function driver($driver = null): object
    {
        /** @var TDriver */
        return parent::driver($driver);
    }

    /** The config key that names the driver. */
    abstract protected function configKey(): string;

    /**
     * @return TDriver
     *
     * @throws InvalidConfiguration
     */
    #[\Override]
    protected function createDriver($driver): object
    {
        if (! isset($this->customCreators[$driver]) && ! method_exists($this, 'create'.Str::studly($driver).'Driver')) {
            throw InvalidConfiguration::unknownDriver($this->configKey(), $driver);
        }

        /** @var TDriver */
        return parent::createDriver($driver);
    }
}
