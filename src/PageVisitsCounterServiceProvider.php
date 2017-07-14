<?php

namespace Cyrildewit\PageVisitsCounter;

use Illuminate\Support\ServiceProvider;
use Cyrildewit\PageVisitsCounter\Contracts\PageVisit as PageVisitContract;

/**
 * Class PageVisitsCounterServiceProvider.
 *
 * @copyright  Copyright (c) 2017 Cyril de Wit (http://www.cyrildewit.nl)
 * @author     Cyril de Wit (info@cyrildewit.nl)
 * @license    https://opensource.org/licenses/MIT    MIT License
 */
class PageVisitsCounterServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap the application services.
     *
     * @return void
     */
    public function boot()
    {
        $this->publishes([
            __DIR__.'/../config/page-visits-counter.php' => $this->app->configPath('page-visits-counter.php'),
        ], 'config');

        // Publish migration file only if it doesn't exists
        if (! class_exists('CreatePageVisitsTable')) {
            $timestamp = date('Y_m_d_His', time());

            $this->publishes([
                __DIR__.'/../database/migrations/create_page_visits_table.php.stub' => $this->app->databasePath("migrations/{$timestamp}_create_page_visits_table.php"),
            ], 'migrations');
        }

        $this->registerModelBindings();
    }

    /**
     * Regiser the application services.
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
     * Register Model Bindings.
     *
     * @return void
     */
    protected function registerModelBindings()
    {
        $config = $this->app->config['page-visits-counter'];

        $this->app->bind(PageVisitContract::class, $config['page_visit_model']);
    }
}
