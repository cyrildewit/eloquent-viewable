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

use Cookie;
use Request;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Database\Eloquent\Builder;
use CyrildeWit\EloquentViewable\Support\Period;
use CyrildeWit\EloquentViewable\Jobs\ProcessView;
use CyrildeWit\EloquentViewable\Support\IpAddress;
use CyrildeWit\EloquentViewable\Contracts\CrawlerDetector;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use CyrildeWit\EloquentViewable\Contracts\View as ViewContract;
use CyrildeWit\EloquentViewable\Contracts\ViewableService as ViewableServiceContract;

/**
 * Class ViewableService.
 *
 * @author Cyril de Wit <github@cyrildewit.nl>
 */
class ViewableService implements ViewableServiceContract
{
    /**
     * The cache repository instance.
     *
     * @var \Illuminate\Contracts\Cache\Repository
     */
    protected $cache;

    /**
     * The crawler detector instance.
     *
     * @var \CyrildeWit\EloquentViewable\Contracts\CrawlerDetector
     */
    protected $crawlerDetector;

    /**
     * IpAddress instance.
     *
     * @var \CyrildeWit\EloquentViewable\Support\IpAddress
     */
    protected $ipRepository;

    /**
     * Create a new ViewableService instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->cache = app(CacheRepository::class);
        $this->crawlerDetector = app(CrawlerDetector::class);
        $this->ipRepository = app(IpAddress::class);
    }

    /**
     * Get the views count based upon the given arguments.
     *
     * @param  \Illuminate\Database\Eloquent\Model  $viewable
     * @param  \DateTime  $sinceDateTime
     * @param  \DateTime  $uptoDateTime
     * @param  bool  $unique
     * @return int
     */
    public function getViewsCount($viewable, $period = null, bool $unique = false)
    {
        // Retrieve configuration
        $cachingEnabled = config('eloquent-viewable.cache.enabled', true);
        $cachingViewsCountEnabled = config('eloquent-viewable.cache.cache_views_count.enabled', true);

        // Use inserted period, otherwise create an empty one
        $period = $period ?? Period::create();

        // Make a unique key for caching
        $cacheKey = $this->createCacheDateTimesKey($viewable, $period, $unique);

        // Check cache if wanted
        if ($cachingEnabled && $cachingViewsCountEnabled) {
            $cachedViewsCount = $this->cache->get($cacheKey);

            if ($cachedViewsCount !== null) {
                return $cachedViewsCount;
            }
        }

        // Count the views again
        $viewsCount = $this->countViews($viewable, $period->getStartDateTime(), $period->getEndDateTime(), $unique);

        // Cache the counted views
        if ($cachingEnabled) {
            $lifetime = config('eloquent-viewable.cache.cache_views_count.lifetime_in_minutes', 60);
            $this->cache->put($cacheKey, $viewsCount, $lifetime);
        }

        return $viewsCount;
    }

    /**
     * Get the unique views count based upon the given arguments.
     *
     * @param  \Illuminate\Database\Eloquent\Model  $viewable
     * @param  \DateTime|null  $sinceDateTime
     * @param  \DateTime|null  $uptoDateTime
     * @return int
     */
    public function getUniqueViewsCount($viewable, $period = null): int
    {
        return $this->getViewsCount($viewable, $period, true);
    }

    /**
     * Count the views based upon the given arguments.
     *
     * @param  \Illuminate\Database\Eloquent\Model  $viewable
     * @param  \DateTime  $startDateTime
     * @param  \DateTime  $endDateTime
     * @param  bool  $unique
     * @return int
     */
    public function countViews($viewable, $startDateTime = null, $endDateTime = null, bool $unique = false): int
    {
        // Create new Query Builder instance of the views relationship
        $query = $viewable->views();

        // Apply the following date filters
        if ($startDateTime && ! $endDateTime) {
            $query->where('viewed_at', '>=', $startDateTime);
        } elseif (! $startDateTime && $endDateTime) {
            $query->where('viewed_at', '<=', $endDateTime);
        } elseif ($startDateTime && $endDateTime) {
            $query->whereBetween('viewed_at', [$startDateTime, $endDateTime]);
        }

        // Count all the views
        if (! $unique) {
            $viewsCount = $query->count();
        }

        // Count only the unique views
        if ($unique) {
            $viewsCount = $query->distinct('visitor')->count('visitor');
        }

        return $viewsCount;
    }

    /**
     * Store a new view.
     *
     * @param  \Illuminate\Database\Eloquent\Model  $viewable
     * @return bool
     */
    public function addViewTo($viewable): bool
    {
        $ignoreBots = config('eloquent-viewable.ignore_bots', true);
        $honorToDnt = config('eloquent-viewable.honor_dnt', false);
        $cookieName = config('eloquent-viewable.cookie_name', 'eloquent_viewable');

        // If ignore bots is true and the current viewer is a bot, return false
        if ($ignoreBots && $this->crawlerDetector->isBot()) {
            return false;
        }

        // If we honor to the DNT header and the current request contains the
        // DNT header, return false
        if ($honorToDnt && (Request::header('HTTP_DNT') == 1)) {
            return false;
        }

        $ignoredIpAddresses = Collection::make(config('eloquent-viewable.ignored_ip_addresses', []));

        if ($ignoredIpAddresses->contains($this->ipRepository->get())) {
            return false;
        }

        $visitorCookie = Cookie::get($cookieName);
        $visitor = $visitorCookie ?? $this->ipRepository->get();

        // Create a new View model instance
        $view = app(ViewContract::class)->create([
            'viewable_id' => $viewable->getKey(),
            'viewable_type' => $viewable->getMorphClass(),
            'visitor' => $visitor,
            'viewed_at' => Carbon::now(),
        ]);

        // If queuing is enabled, dispatch the job
        $configStoreNewView = config('eloquent-viewable.jobs.store_new_view');

        if ($configStoreNewView['enabled']) {
            $delayInSeconds = $configStoreNewView['delay_in_seconds'] ?? 60 * 2;
            $onQueue = $configStoreNewView['onQueue'] ?? null;
            $onConnection = $configStoreNewView['onConnection'] ?? null;

            ProcessView::dispatch($view)
                ->delay(Carbon::now()->addSeconds($delayInSeconds))
                ->onQueue($onQueue)
                ->onConnection($onConnection);

            return true;
        }

        // Otherwise, just save the view in the database
        $view->save();

        return true;
    }

    /**
     * Remove all views from a viewable model.
     *
     * @param  \Illuminate\Database\Eloquent\Model  $viewable
     * @return void
     */
    public function removeModelViews($viewable)
    {
        app(ViewContract::class)->where([
            'viewable_id' => $viewable->getKey(),
            'viewable_type' => $viewable->getMorphClass(),
        ])->delete();
    }

    /**
     * Retrieve records sorted by views count.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @param  string  $direction
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function applyScopeOrderByViewsCount(Builder $query, string $direction = 'desc'): Builder
    {
        $viewable = $query->getModel();
        $viewModel = app(ViewContract::class);

        return $query->leftJoin($viewModel->getTable(), "{$viewModel->getTable()}.viewable_id", '=', "{$viewable->getTable()}.id")
            ->selectRaw("{$viewable->getTable()}.*, count({$viewModel->getTable()}.{$viewModel->getKeyName()}) as aggregate")
            ->groupBy("{$viewable->getTable()}.{$viewable->getKeyName()}")
            ->orderBy('aggregate', $direction);
    }

    /**
     * Create a views count cache key.
     *
     * @param  \Illuminate\Database\Eloquent\Model  $viewable
     * @param  \CyrildeWit\EloquentViewable\Support\Period  $period
     * @param  bool  $unique
     * @return string
     */
    protected function createCacheDateTimesKey($viewable, $period, bool $unique): string
    {
        $cacheKey = config('eloquent-viewable.cache.key', 'cyrildewit.eloquent-viewable.cache');

        $viewableId = $viewable->getKey();
        $viewableType = strtolower(str_replace('\\', '-', $viewable->getMorphClass()));

        $typeKey = $unique ? 'unique' : 'normal';
        $periodKey = $period->makeKey();

        return "{$cacheKey}.{$viewableType}.{$viewableId}.{$typeKey}.{$periodKey}";
    }
}
