<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Tests;

use CyrildeWit\EloquentViewable\EloquentViewableServiceProvider;
use Illuminate\Support\Facades\File;
use Orchestra\Testbench\Attributes\WithEnv;
use Orchestra\Testbench\TestCase as OrchestraTestCase;

#[WithEnv('DB_CONNECTION', 'testing')]
abstract class TestCase extends OrchestraTestCase
{
    protected function getPackageProviders($app): array
    {
        return [
            EloquentViewableServiceProvider::class,
        ];
    }

    protected function defineDatabaseMigrations(): void
    {
        // Start from a clean slate, publish and migrate the package's own
        // migrations, then migrate the models used only by the test suite.
        File::cleanDirectory('vendor/orchestra/testbench-core/laravel/database/migrations');

        $this->artisan('vendor:publish', [
            '--force' => '',
            '--tag' => 'migrations',
        ]);

        $this->loadMigrationsFrom([
            '--realpath' => true,
        ]);

        $this->loadMigrationsFrom(__DIR__.'/database/migrations');
    }
}
