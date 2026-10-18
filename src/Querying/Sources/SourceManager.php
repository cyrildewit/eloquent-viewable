<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Querying\Sources;

use CyrildeWit\EloquentViewable\Querying\Contracts\ViewSource;
use CyrildeWit\EloquentViewable\Support\Config;
use CyrildeWit\EloquentViewable\Support\PackageManager;

/** @extends PackageManager<ViewSource, Config> */
final class SourceManager extends PackageManager
{
    public function getDefaultDriver(): string
    {
        return $this->packageConfig->sourceDriver();
    }

    protected function configKey(): string
    {
        return 'querying.source.driver';
    }

    protected function createDatabaseDriver(): DatabaseSource
    {
        return $this->container->make(DatabaseSource::class);
    }
}
