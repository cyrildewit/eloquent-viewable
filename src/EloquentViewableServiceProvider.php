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
use Illuminate\Support\Facades\Schema;

class EloquentViewableServiceProvider extends ServiceProvider
{
    /**
     * Perform post-registration booting of services.
     *
     * @return void
     */
    public function boot()
    {
        // $this->registerMiddleware();
        $this->registerMigrations();
        $this->registerPublishing();

        $this->registerContracts();
        $this->registerRoutes();
        // $this->registerPublishing();
        // $this->registerMigrations();
    }

    /**
     * Register bindings in the container.
     *
     * @return void
     */
    public function register()
    {
        $this->mergeConfig();
        // $this->registerConsoleCommands();
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
        // $this->app->singleton(ViewServiceContract::class, ViewService::class);
        $this->app->bind(Views::class);

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
     * Register the package's migrations.
     *
     * @return void
     */
    protected function registerMigrations()
    {
        if ($this->app->runningInConsole()) {
            $this->loadMigrationsFrom(__DIR__.'/../migrations');
        }
    }

    /**
     * Setup the resource publishing groups for Eloquent Viewable.
     *
     * @return void
     */
    protected function registerPublishing()
    {
        if ($this->app->runningInConsole()) {
            $config = $this->app->config['eloquent-viewable'];

            $this->publishes([
                __DIR__.'/../config/eloquent-viewable.php' => $this->app->configPath('eloquent-viewable.php'),
            ], 'config');
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
            __DIR__.'/../config/eloquent-viewable.php',
            'eloquent-viewable'
        );
    }
}
