<?php

namespace CyrildeWit\PageViewCounter;

use Illuminate\Support\ServiceProvider;
use CyrildeWit\PageViewCounter\Contracts\PageView as PageViewContract;

/**
 * Class PageViewCounterServiceProvider.
 *
 * @copyright  Copyright (c) 2017 Cyril de Wit (http://www.cyrildewit.nl)
 * @author     Cyril de Wit (info@cyrildewit.nl)
 * @license    https://opensource.org/licenses/MIT    MIT License
 */
class PageViewCounterServiceProvider extends ServiceProvider
{
    /**
     * Perform post-registration booting of services.
     *
     * @return void
     */
    public function boot()
    {
        // Publish config files
        $this->publishes([
            __DIR__.'/../config/page-view-counter.php' => $this->app->configPath('page-view-counter.php'),
        ], 'config');

        // Publish the migration file only if it doesn't exists
        if (! class_exists('CreatePageViewsTable')) {
            $timestamp = date('Y_m_d_His', time());

            $this->publishes([
                __DIR__.'/../database/migrations/create_page_views_table.php.stub' => $this->app->databasePath("migrations/{$timestamp}_create_page_views_table.php"),
            ], 'migrations');
        }

        // Register the required model bindings
        $this->registerModelBindings();
    }

    /**
     * Register bindings in the container.
     *
     * @return void
     */
    public function register()
    {
        // Merge the main config file
        $this->mergeConfigFrom(
            __DIR__.'/../config/page-view-counter.php',
            'page-view-counter'
        );
    }

    /**
     * Register the model bindings.
     *
     * @return void
     */
    protected function registerModelBindings()
    {
        $config = $this->app->config['page-view-counter'];

        $this->app->bind(PageViewContract::class, $config['page_view_model']);
    }
}
