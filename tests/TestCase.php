<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Tests;

use Carbon\Carbon;
use CyrildeWit\EloquentViewable\EloquentViewableServiceProvider;
use Illuminate\Support\Facades\File;
use Mockery;
use Orchestra\Testbench\TestCase as Orchestra;

class TestCase extends Orchestra
{
    public function setUp(): void
    {
        parent::setUp();

        $this->destroyPackageMigrations();
        $this->publishPackageMigrations();
        $this->migratePackageTables();
        $this->migrateUnitTestTables();
    }

    protected function tearDown(): void
    {
        Mockery::close();
        Carbon::setTestNow();

        parent::tearDown();
    }

    protected function getPackageProviders($app): array
    {
        return [
            EloquentViewableServiceProvider::class,
        ];
    }

    protected function publishPackageMigrations(): void
    {
        $this->artisan('vendor:publish', [
            '--force' => '',
            '--tag' => 'migrations',
        ]);
    }

    protected function destroyPackageMigrations(): void
    {
        File::cleanDirectory('vendor/orchestra/testbench-core/laravel/database/migrations');
    }

    protected function migratePackageTables(): void
    {
        $this->loadMigrationsFrom([
            '--realpath' => true,
        ]);
    }

    protected function migrateUnitTestTables(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/database/migrations');
    }

    protected function getEnvironmentSetUp($app): void
    {
        $app['config']->set('database.default', 'sqlite');
        $app['config']->set('database.connections.sqlite', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);
    }
}
