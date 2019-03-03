<?php

declare(strict_types=1);

/*
 * This file is part of the Eloquent Viewable package.
 *
 * (c) Cyril de Wit <github@cyrildewit.nl>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace CyrildeWit\EloquentViewable\Tests;

use Carbon\Carbon;
use Illuminate\Support\Facades\File;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    /**
     * Setup the test environment.
     *
     * @return void
     */
    public function setUp()
    {
        parent::setUp();

        $this->destroyPackageMigrations();
        $this->publishPackageMigrations();
        $this->migratePackageTables();
        $this->migrateUnitTestTables();
        $this->registerPackageFactories();
    }

    /**
     * Clean up the testing environment before the next test.
     *
     * @return void
     */
    protected function tearDown()
    {
        Carbon::setTestNow();
    }

    /**
     * Load package service provider.
     *
     * @param \Illuminate\Foundation\Application  $app
     * @return array
     */
    protected function getPackageProviders($app)
    {
        return [
            \Orchestra\Database\ConsoleServiceProvider::class,
            \CyrildeWit\EloquentViewable\EloquentViewableServiceProvider::class,
        ];
    }

    /**
     * Publish package migrations.
     *
     * @return void
     */
    protected function publishPackageMigrations()
    {
        $this->artisan('vendor:publish', [
            '--force' => '',
            '--tag' => 'migrations',
        ]);
    }

    /**
     * Delete all published migrations.
     *
     * @return void
     */
    protected function destroyPackageMigrations()
    {
        File::cleanDirectory('vendor/orchestra/testbench-core/laravel/database/migrations');
    }

    /**
     * Perform package database migrations.
     *
     * @return void
     */
    protected function migratePackageTables()
    {
        $this->loadMigrationsFrom([
            '--realpath' => database_path('migrations'),
        ]);
    }

    /**
     * Perform unit test database migrations.
     *
     * @return void
     */
    protected function migrateUnitTestTables()
    {
        $this->loadMigrationsFrom(__DIR__.'/database/migrations');
    }

    /**
     * Register package related model factories.
     *
     * @return void
     */
    protected function registerPackageFactories()
    {
        $this->withFactories(realpath(__DIR__.'/database/factories'));
    }
}
