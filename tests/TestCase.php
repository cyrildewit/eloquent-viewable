<?php

declare(strict_types=1);

/*
 * This file is part of Eloquent Viewable.
 *
 * (c) Cyril de Wit <github@cyrildewit.nl>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace CyrildeWit\EloquentViewable\Tests;

use Config;
use Carbon\Carbon;
use Illuminate\Support\Facades\File;
use Orchestra\Testbench\TestCase as Orchestra;

/**
 * This is the abstract test case class.
 *
 * @author Cyril de Wit <github@cyrildewit.nl>
 */
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
        $this->setCustomConfiguration();
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
            \CyrildeWit\EloquentViewable\EloquentViewableServiceProvider::class,
            \Orchestra\Database\ConsoleServiceProvider::class,
        ];
    }

    /**
     * Configure the test environment.
     *
     * @
     */
    protected function setCustomConfiguration()
    {
        // Set a fixed date for Carbon::now()
        Carbon::setTestNow(Carbon::create(2020, 1, 1, 0, 0, 0));

        // Turn caching off
        Config::set('eloquent-viewable.cache.enabled', false);
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
        $this->loadMigrationsFrom([
            '--realpath' => realpath(__DIR__.'/database/migrations'),
        ]);
    }

    /**
     * Register package related model factories.
     *
     * @return void
     */
    protected function registerPackageFactories()
    {
        $pathToFactories = realpath(__DIR__.'/database/factories');
        $this->withFactories($pathToFactories);
    }
}
