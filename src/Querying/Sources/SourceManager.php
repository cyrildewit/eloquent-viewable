<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Querying\Sources;

use CyrildeWit\EloquentViewable\Exceptions\InvalidConfiguration;
use CyrildeWit\EloquentViewable\Querying\Contracts\ViewSource;
use CyrildeWit\EloquentViewable\Support\Config;
use Illuminate\Contracts\Container\Container;
use Illuminate\Support\Manager;
use Illuminate\Support\Str;

/** @method ViewSource driver(?string $driver = null) */
final class SourceManager extends Manager
{
    public function __construct(Container $container, private readonly Config $packageConfig)
    {
        parent::__construct($container);
    }

    public function getDefaultDriver(): string
    {
        return $this->packageConfig->sourceDriver();
    }

    /**
     * @param  string  $driver
     *
     * @throws InvalidConfiguration
     */
    #[\Override]
    protected function createDriver($driver): ViewSource
    {
        if (! isset($this->customCreators[$driver]) && ! method_exists($this, 'create'.Str::studly($driver).'Driver')) {
            throw InvalidConfiguration::unknownDriver('querying.source.driver', $driver);
        }

        /** @var ViewSource $source */
        $source = parent::createDriver($driver);

        return $source;
    }

    protected function createDatabaseDriver(): DatabaseSource
    {
        return $this->container->make(DatabaseSource::class);
    }
}
