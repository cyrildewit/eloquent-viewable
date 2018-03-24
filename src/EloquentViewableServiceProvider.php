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

namespace CyrildeWit\EloquentViewable;

use Illuminate\Support\ServiceProvider;
use CyrildeWit\EloquentViewable\Models\View;
use CyrildeWit\EloquentViewable\Services\ViewableService;
use CyrildeWit\EloquentViewable\Contracts\Models\View as ViewContract;
use CyrildeWit\EloquentViewable\Contracts\Services\ViewableService as ViewableServiceContract;

/**
 * Class ServiceProvider.
 *
 * @author Cyril de Wit <github@cyrildewit.nl>
 */
class EloquentViewableServiceProvider extends ServiceProvider
{
    /**
     * Indicates if loading of the provider is deferred.
     *
     * @var bool
     */
    protected $defer = false;

    /**
     * Perform post-registration booting of services.
     *
     * @return void
     */
    public function boot()
    {
        // $this->registerConsoleCommands();
        $this->registerContracts();
        // $this->registerObservers();
        $this->registerRoutes();
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
        $this->app->bind(ViewContract::class, View::class);
        $this->app->singleton(ViewableServiceContract::class, ViewableService::class);
    }

    /**
     * Register the required routes for Eloquent Viewable.
     *
     * @return void
     */
    protected function registerRoutes()
    {
        $namespace = $this->app->getNamespace();

        $this->app->router->group(['namespace' => $namespace.'Http\Controllers'], function () {
            require __DIR__.'/Http/routes.php';
        });
    }

    /**
     * Setup the resource publishing groups for Eloquent Viewable.
     *
     * @return void
     */
    protected function registerPublishes()
    {
        // If the application is not running in the console, stop with executing
        // this method.
        if (! $this->app->runningInConsole()) {
            return;
        }

        $config = $this->app->config['eloquent-viewable'];

        $this->publishes([
            __DIR__.'/../resources/config/eloquent-viewable.php' => $this->app->configPath('eloquent-viewable.php'),
        ], 'config');

        // Publish the `CreateViewsTable` migration if it doesn't exists
        if (! class_exists('CreateViewsTable')) {
            $timestamp = date('Y_m_d_His', time());
            $viewsTableName = snake_case($config['table_names']['views']);

            $this->publishes([
                __DIR__.'/../resources/database/migrations/create_views_table.php.stub' => $this->app->databasePath("migrations/{$timestamp}_create_{$viewsTableName}_table.php"),
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
            __DIR__.'/../resources/config/eloquent-viewable.php',
            'eloquent-viewable'
        );
    }
}
