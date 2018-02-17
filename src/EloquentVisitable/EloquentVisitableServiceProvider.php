<?php

declare(strict_types=1);

/*
 * This file is part of Eloquent Visitable.
 *
 * (c) Cyril de Wit <github@cyrildewit.nl>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace CyrildeWit\EloquentVisitable;

use Illuminate\Support\ServiceProvider;
use CyrildeWit\EloquentVisitable\Services\VisitService;
use CyrildeWit\EloquentVisitable\Contracts\Models\Visit as VisitContract;

/**
 * This is the eloquent visitable service provider class.
 *
 * @author Cyril de Wit <github@cyrildewit.nl>
 */
class EloquentVisitableServiceProvider extends ServiceProvider
{
    /**
     * Perform post-registration booting of services.
     *
     * @return void
     */
    public function boot()
    {
        $this->registerConsoleCommands();
        $this->registerContracts();
        $this->registerPublishes();
    }

    /**
     * Register bindings in the container.
     *
     * @return void
     */
    public function register()
    {
       $this->mergeConfig();
    }

    /**
     * Register the artisan commands.
     *
     * @return void
     */
    protected function registerConsoleCommands()
    {
        //
    }

    /**
     * Register the model bindings.
     *
     * @return void
     */
    protected function registerContracts()
    {
        $config = $this->app->config['eloquent-visitable'];

        $this->app->bind(VisitContract::class, $config['models']['visit']);
    }

    /**
     * Setup the resource publishing groups for Eloquent Visitable.
     *
     * @return void
     */
    protected function registerPublishes()
    {
        $config = $this->app->config['eloquent-visitable'];

        // Publish the Eloquent Visitable config file
        $this->publishes([
            __DIR__.'/../config/eloquent-visitable.php' => $this->app->configPath('eloquent-visitable.php'),
        ], 'config');

        // Publish the Eloquent Visitable config file
        $this->publishes([
            __DIR__.'/../config/eloquent-visitable.php' => $this->app->configPath('eloquent-visitable.php'),
        ], 'config');

        // Publish the `CreateVisitsTable` migration if it doesn't exists
        if (! class_exists('CreateVisitsTable')) {
            $timestamp = date('Y_m_d_His', time());
            $visitsTableName = snake_case($config['table_names']['visits']);

            $this->publishes([
                __DIR__.'/../database/migrations/create_visits_table.php.stub' => $this->app->databasePath("migrations/{$timestamp}_create_{$visitsTableName}_table.php"),
            ], 'migrations');
        }
    }

    /**
     * Merge the user's config file.
     *
     * @return void
     */
    protected function mergeConfig()
    {
        $this->mergeConfigFrom(
            __DIR__.'/../config/eloquent-visitable.php',
            'eloquent-visitable'
        );
    }
}
