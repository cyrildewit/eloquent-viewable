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
use Illuminate\Cache\Repository as CacheRepository;
use CyrildeWit\EloquentViewable\Resolvers\HeaderResolver;
use CyrildeWit\EloquentViewable\Resolvers\IpAddressResolver;
use CyrildeWit\EloquentViewable\Contracts\View as ViewContract;
use CyrildeWit\EloquentViewable\Contracts\HeaderResolver as HeaderResolverContract;
use CyrildeWit\EloquentViewable\Contracts\CrawlerDetector as CrawlerDetectorContract;
use CyrildeWit\EloquentViewable\Contracts\IpAddressResolver as IpAddressResolverContract;

class EloquentViewableServiceProvider extends ServiceProvider
{
    /**
     * Perform post-registration booting of services.
     *
     * @return void
     */
    public function boot()
    {
        if ($this->app->runningInConsole()) {
            $config = $this->app->config['eloquent-viewable'];

            $this->publishes([
                __DIR__.'/../config/eloquent-viewable.php' => $this->app->configPath('eloquent-viewable.php'),
            ], 'config');

            if (! class_exists('CreateViewsTable')) {
                $timestamp = date('Y_m_d_His', time());

                $this->publishes([
                    __DIR__.'/../migrations/create_views_table.php.stub' => database_path("/migrations/{$timestamp}_create_views_table.php"),
                ], 'migrations');
            }
        }
    }

    /**
     * Register bindings in the container.
     *
     * @return void
     */
    public function register()
    {
        $this->mergeConfigFrom(
            __DIR__.'/../config/eloquent-viewable.php',
            'eloquent-viewable'
        );

        $this->app->when(Views::class)
            ->needs(CacheRepository::class)
            ->give(function () : CacheRepository {
                return $this->app['cache']->store(config('eloquent-viewable.cache.store'));
            });

        $this->app->bind(ViewContract::class, View::class);

        $this->app->bind(CrawlerDetectAdapter::class, function ($app) {
            $detector = new CrawlerDetect(
                $app['request']->headers->all(),
                $app['request']->server('HTTP_USER_AGENT')
            );

            return new CrawlerDetectAdapter($detector);
        });

        $this->app->singleton(CrawlerDetectorContract::class, CrawlerDetectAdapter::class);
        $this->app->singleton(IpAddressResolverContract::class, IpAddressResolver::class);
        $this->app->singleton(HeaderResolverContract::class, HeaderResolver::class);
    }
}
