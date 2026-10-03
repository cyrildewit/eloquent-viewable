<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable;

use CyrildeWit\EloquentViewable\Cooldowns\Contracts\CooldownStore;
use CyrildeWit\EloquentViewable\Cooldowns\CooldownManager;
use CyrildeWit\EloquentViewable\Crawlers\Contracts\CrawlerDetector as CrawlerDetectorContract;
use CyrildeWit\EloquentViewable\Crawlers\Detectors\CrawlerDetectAdapter;
use CyrildeWit\EloquentViewable\Exceptions\InvalidConfiguration;
use CyrildeWit\EloquentViewable\Http\Middleware\RecordViews;
use CyrildeWit\EloquentViewable\Models\View;
use CyrildeWit\EloquentViewable\Querying\Cache\CacheVersions;
use CyrildeWit\EloquentViewable\Querying\Cache\VersionedCache;
use CyrildeWit\EloquentViewable\Querying\Contracts\ViewSource;
use CyrildeWit\EloquentViewable\Querying\Grammars\GrammarRegistry;
use CyrildeWit\EloquentViewable\Querying\Grammars\MySqlGrammar;
use CyrildeWit\EloquentViewable\Querying\Grammars\PostgresGrammar;
use CyrildeWit\EloquentViewable\Querying\Grammars\SQLiteGrammar;
use CyrildeWit\EloquentViewable\Querying\Sources\SourceManager;
use CyrildeWit\EloquentViewable\Recording\Actions\RecordView;
use CyrildeWit\EloquentViewable\Recording\Console\FlushViewsCommand;
use CyrildeWit\EloquentViewable\Recording\Contracts\RecordingGuard;
use CyrildeWit\EloquentViewable\Recording\Contracts\RecordsViews as RecordsViewsContract;
use CyrildeWit\EloquentViewable\Recording\Contracts\ViewStore;
use CyrildeWit\EloquentViewable\Recording\Events\ViewsDestroyed;
use CyrildeWit\EloquentViewable\Recording\Recorder;
use CyrildeWit\EloquentViewable\Recording\Stores\StoreManager;
use CyrildeWit\EloquentViewable\Support\Config;
use CyrildeWit\EloquentViewable\Visitors\Contracts\Visitor as VisitorContract;
use CyrildeWit\EloquentViewable\Visitors\Visitor;
use CyrildeWit\EloquentViewable\Visitors\VisitorIdentity;
use Illuminate\Contracts\Bus\Dispatcher as BusDispatcher;
use Illuminate\Contracts\Cache\Factory as CacheFactory;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Contracts\Events\Dispatcher as EventDispatcher;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Routing\Router;
use Illuminate\Support\ServiceProvider;
use Jaybizzle\CrawlerDetect\CrawlerDetect;

class EloquentViewableServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->callAfterResolving('router', function (Router $router): void {
            $router->aliasMiddleware(RecordViews::ALIAS, RecordViews::class);
        });

        // Recording does not know about the cache, so the counts remembered of
        // destroyed views are forgotten here, where both sides meet.
        $this->app->make(EventDispatcher::class)->listen(
            ViewsDestroyed::class,
            fn (ViewsDestroyed $event) => $this->app->make(CacheVersions::class)->forgetCache($event->viewable),
        );

        if ($this->app->runningInConsole()) {
            $this->commands([FlushViewsCommand::class]);

            $this->publishes([
                __DIR__.'/../config/eloquent-viewable.php' => $this->app->configPath('eloquent-viewable.php'),
            ], 'config');

            if (! class_exists('CreateViewsTable')) {
                $timestamp = date('Y_m_d_His', time());

                $this->publishes([
                    __DIR__.'/../database/migrations/create_views_table.php.stub' => $this->app->databasePath("migrations/{$timestamp}_create_views_table.php"),
                ], 'migrations');
            }
        }
    }

    #[\Override]
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__.'/../config/eloquent-viewable.php',
            'eloquent-viewable'
        );

        $this->registerCore();
        $this->registerRecording();
        $this->registerQuerying();
    }

    protected function registerCore(): void
    {
        $this->app->singleton(Config::class);

        $this->app->bind(View::class, function (Application $app): View {
            $model = $app->make(Config::class)->viewModel();

            return new $model;
        });

        $this->app->when([VersionedCache::class, CacheVersions::class])
            ->needs(CacheRepository::class)
            ->give(fn (): CacheRepository => $this->app->make(CacheFactory::class)->store(
                $this->app->make(Config::class)->cacheStore()
            ));
    }

    protected function registerRecording(): void
    {
        $this->app->singleton(StoreManager::class);

        $this->app->bind(ViewStore::class, fn (Application $app): ViewStore => $app->make(StoreManager::class)->driver());

        $this->app->bind(RecordsViewsContract::class, RecordView::class);

        $this->app->bind(Recorder::class, function (Application $app): Recorder {
            $config = $app->make(Config::class);

            return new Recorder(
                $this->resolveGuards($app, $config),
                $config,
                $app->make(BusDispatcher::class),
                $app->make(EventDispatcher::class),
                $app->make(RecordsViewsContract::class),
                $app->make(VisitorIdentity::class),
            );
        });

        $this->app->bind(VisitorContract::class, Visitor::class);

        $this->app->singleton(CooldownManager::class);

        $this->app->bind(CooldownStore::class, fn (Application $app): CooldownStore => $app->make(CooldownManager::class)->driver());

        // The detector judges the user agent it is handed, so it holds no
        // request state and one instance serves the whole process. The
        // library compiles its pattern list once in the constructor.
        $this->app->singleton(CrawlerDetect::class);
        $this->app->singleton(CrawlerDetectorContract::class, CrawlerDetectAdapter::class);
    }

    /**
     * @return list<RecordingGuard>
     *
     * @throws InvalidConfiguration
     */
    protected function resolveGuards(Application $app, Config $config): array
    {
        $guards = [];

        foreach ($config->guards() as $class) {
            $guard = $app->make($class);

            if (! $guard instanceof RecordingGuard) {
                throw InvalidConfiguration::mustImplement('recording.guards', RecordingGuard::class, $class);
            }

            $guards[] = $guard;
        }

        return $guards;
    }

    protected function registerQuerying(): void
    {
        $this->app->singleton(SourceManager::class);

        $this->app->bind(ViewSource::class, fn (Application $app): ViewSource => $app->make(SourceManager::class)->driver());

        $this->app->singleton(GrammarRegistry::class, function (): GrammarRegistry {
            $grammars = new GrammarRegistry;

            $grammars->register('sqlite', SQLiteGrammar::class);
            $grammars->register('mysql', MySqlGrammar::class);
            $grammars->register('mariadb', MySqlGrammar::class);
            $grammars->register('pgsql', PostgresGrammar::class);

            return $grammars;
        });
    }
}
