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

namespace CyrildeWit\EloquentViewable;

use Illuminate\Support\ServiceProvider;
use Jaybizzle\CrawlerDetect\CrawlerDetect;
use CyrildeWit\EloquentViewable\Contracts\CrawlerDetector;
use CyrildeWit\EloquentViewable\Contracts\View as ViewContract;
use CyrildeWit\EloquentViewable\Contracts\ViewableService as ViewableServiceContract;

/**
 * Class EloquentViewableServiceProvider.
 *
 * @author Cyril de Wit <github@cyrildewit.nl>
 */
class EloquentViewableServiceProvider extends ServiceProvider
{
    /**
     * Perform post-registration booting of services.
     *
     * @return void
     */
    public function boot()
    {
        // $this->registerConsoleCommands();
        $this->registerContracts();
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

        $this->app->bind(CrawlerDetectAdapter::class, function ($app) {
            $detector = new CrawlerDetect(
                $app['request']->headers->all(),
                $app['request']->server('HTTP_USER_AGENT')
            );

            return new CrawlerDetectAdapter($detector);
        });

        $this->app->singleton(CrawlerDetector::class, CrawlerDetectAdapter::class);
    }

    /**
     * Register the required routes for Eloquent Viewable.
     *
     * @return void
     */
    protected function registerRoutes()
    {
        $this->loadRoutesFrom(__DIR__.'/../routes/eloquent-viewable.php');
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
            __DIR__.'/../publishable/config/eloquent-viewable.php' => $this->app->configPath('eloquent-viewable.php'),
        ], 'config');

        // Publish the `CreateViewsTable` migration if it doesn't exists
        if (! class_exists('CreateViewsTable')) {
            $timestamp = date('Y_m_d_His', time());
            $viewsTableName = snake_case($config['models']['view']['table_name']);

            $this->publishes([
                __DIR__.'/../publishable/database/migrations/create_views_table.php.stub' => $this->app->databasePath("migrations/{$timestamp}_create_{$viewsTableName}_table.php"),
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
            __DIR__.'/../publishable/config/eloquent-viewable.php',
            'eloquent-viewable'
        );
    }
}
