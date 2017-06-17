<?php

namespace Cyrildewit\PageVisitsCounter;

use Illuminate\Support\ServiceProvider;
use Cyrildewit\PageVisitsCounter\Contracts\PageVisit as PageVisitContract;

class PageVisitsCounterServiceProvider extends ServiceProvider
{
    /**
     * Register bindings in the container.
     *
     * @return void
     */
    public function register()
    {
        // Merge the config file
        $this->mergeConfigFrom(
            __DIR__.'/../config/page-visits-counter.php',
            'page-visits-counter'
        );
    }

    /**
     * Bootstrap PageVisitsCounter application services.
     *
     * @return void
     */
    public function boot()
    {
        // Publish config file
        $this->publishes([
            __DIR__.'/../config/page-visits-counter.php' => $this->app->configPath('page-visits-counter.php'),
        ], 'config');

        // Publish migration file only if it doesn't exist
        if (! class_exists('CreatePageVisitsTable')) {
            $timestamp = date('Y_m_d_His', time());

            $this->publishes([
                __DIR__.'/../database/migrations/create_page_visits_table.php.stub' => $this->app->databasePath("migrations/{$timestamp}_create_page_visits_table.php"),
            ], 'migrations');
        }

        // Register Model Bindings
        $this->registerModelBindings();
    }

    /**
     * Register Model Bindings.
     *
     * @return void
     */
    protected function registerModelBindings()
    {
        $config = $this->app->config['page-visits-counter.models'];

        $this->app->bind(PageVisitContract::class, $config['page-visit']);
    }
}
