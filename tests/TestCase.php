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

        $this->migrateUnitTestTables();
        $this->registerPackageFactories();

        $this->artisan('migrate');
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
        $pathToFactories = realpath(__DIR__.'/database/factories');
        $this->withFactories($pathToFactories);
    }
}
