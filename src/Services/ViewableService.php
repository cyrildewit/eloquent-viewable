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

namespace CyrildeWit\EloquentViewable\Services;

use Cookie;
use Request;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Database\Eloquent\Builder;
use Jaybizzle\CrawlerDetect\CrawlerDetect;
use CyrildeWit\EloquentViewable\Jobs\ProcessView;
use CyrildeWit\EloquentViewable\Cache\ViewsCountCacheRepository;
use CyrildeWit\EloquentViewable\Contracts\Models\View as ViewContract;
use CyrildeWit\EloquentViewable\Contracts\Services\ViewableService as ViewableServiceContract;

/**
 * Class ViewableService.
 *
 * @author Cyril de Wit <github@cyrildewit.nl>
 */
class ViewableService implements ViewableServiceContract
{
    /**
     * ViewsCountCacheRepository instance.
     *
     * @var \CyrildeWit\EloquentViewable\Cache\ViewsCountCacheRepository
     */
    protected $viewsCountCacheRepository;

    /**
     * CrawlerDetect instance.
     *
     * @var \Jaybizzle\CrawlerDetect\CrawlerDetect
     */
    protected $crawlerDetect;

    /**
     * Create a new ViewableService instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->viewsCountCacheRepository = app(ViewsCountCacheRepository::class);
        $this->crawlerDetect = app(CrawlerDetect::class);
    }

    /**
     * Get the views count based upon the given arguments.
     *
     * @param  \Illuminate\Database\Eloquent\Model  $viewable
     * @param  \Carbon\Carbon|null  $sinceDateTime
     * @param  \Carbon\Carbon|null  $uptoDateTime
     * @param  bool  $unique
     * @return int
     */
    public function getViewsCount($viewable, $sinceDateTime = null, $uptoDateTime = null, bool $unique = false): int
    {
        // Key based upon the arguments to retrieve cached views counts
        $viewsCountKey = $this->createStaticDatesKey($viewable, $sinceDateTime, $uptoDateTime, $unique);

        return $this->countAndCacheViewsCount($viewable, $viewsCountKey, $sinceDateTime, $uptoDateTime, $unique);
    }

    /**
     * Get the unique views count based upon the given arguments.
     *
     * @param  \Illuminate\Database\Eloquent\Model  $viewable
     * @param  \Carbon\Carbon|null  $sinceDateTime
     * @param  \Carbon\Carbon|null  $uptoDateTime
     * @return int
     */
    public function getUniqueViewsCount($viewable, $sinceDateTime = null, $uptoDateTime = null): int
    {
        return $this->getViewsCount($viewable, $sinceDateTime, $uptoDateTime, true);
    }

    /**
     * Get the views count of the past period.
     *
     * @param  \Illuminate\Database\Eloquent\Model  $viewable
     * @param  string  $pastType
     * @param  int  $pastValue
     * @param  bool  $unique
     * @return int
     */
    public function getViewsCountOfPast($viewable, $pastType, int $pastValue, bool $unique = false): int
    {
        $sinceDateTime = Carbon::now()->{$pastType}($pastValue);

        // Key based upon the arguments to retrieve cached views counts
        $viewsCountKey = $this->createReactiveDatesKey($viewable, $pastType, $pastValue, $unique);

        return $this->countAndCacheViewsCount($viewable, $viewsCountKey, $sinceDateTime, null, $unique);
    }

    /**
     * Get the unique views count of the past period.
     *
     * @param  \Illuminate\Database\Eloquent\Model  $viewable
     * @param  string  $pastType
     * @param  int  $pastValue
     * @return int
     */
    public function getUniqueViewsCountOfPast($viewable, $pastType, int $pastValue)
    {
        return $this->getViewsCountOfPast($viewable, $pastType, $pastValue, true);
    }

    /**
     * Count the views of the viewable model and store the value under the
     * given key.
     *
     * @param  \Illuminate\Database\Eloquent\Model  $viewable
     * @param  string  $cacheKey
     * @param  \Carbon\Carbon|null  $sinceDateTime
     * @param  \Carbon\Carbon|null  $uptoDateTime
     * @param  bool  $unique
     */
    public function countAndCacheViewsCount($viewable, $cacheKey, $sinceDateTime, $uptoDateTime, $unique)
    {
        $cachingEnabled = config('eloquent-viewable.cache.enabled', true);
        $cachingViewsCountEnabled = config('eloquent-viewable.cache.cache_views_count.enabled', true);

        if ($cachingEnabled && $cachingViewsCountEnabled) {
            if (! is_null($cachedViewsCount = $this->viewsCountCacheRepository->get($cacheKey))) {
                return $cachedViewsCount;
            }
        }

        // Count the views again
        $viewsCount = $this->countViews($viewable, $sinceDateTime, $uptoDateTime, $unique);

        // Cache the counted views
        if ($cachingEnabled) {
            $this->viewsCountCacheRepository->put($cacheKey, $viewsCount);
        }

        return $viewsCount;
    }

    /**
     * Count the views based upon the given arguments.
     *
     * @param  \Illuminate\Database\Eloquent\Model  $viewable
     * @param  \Carbon\Carbon  $sinceDateTime
     * @param  \Carbon\Carbon  $uptoDateTime
     * @param  bool  $unique
     * @return int
     */
    public function countViews($viewable, $sinceDateTime = null, $uptoDateTime = null, bool $unique = false): int
    {
        // Create new Query Builder instance of the views relationship
        $query = $viewable->views();

        // Apply the following date filters
        if ($sinceDateTime && ! $uptoDateTime) {
            $query->where('viewed_at', '>', $sinceDateTime);
        } elseif (! $sinceDateTime && $uptoDateTime) {
            $query->where('viewed_at', '<', $uptoDateTime);
        } elseif ($sinceDateTime && $uptoDateTime) {
            $query->whereBetween('viewed_at', [$sinceDateTime, $uptoDateTime]);
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
        if ($ignoreBots && $this->crawlerDetect->isCrawler()) {
            return false;
        }

        // If we honor to the DNT header and the current request contains the
        // DNT header, return false
        if ($honorToDnt && (Request::header('HTTP_DNT') == 1)) {
            return false;
        }

        $ignoredIpAddresses = Collection::make(config('eloquent-viewable.ignored_ip_addresses'));

        if ($ignoredIpAddresses->contains(Request::ip())) {
            return false;
        }

        $visitorCookie = Cookie::get($cookieName);
        $visitor = $visitorCookie ?? Request::ip();

        // Create a new View model instance
        $view = app(ViewContract::class)->create([
            'viewable_id' => $viewable->getKey(),
            'viewable_type' => $viewable->getMorphClass(),
            'visitor' => $visitor,
        ]);

        // If queuing is enabled, dispatch the job
        $configStoreNewView = config('eloquent-viewable.jobs.store_new_view');

        if ($configStoreNewView['enabled'] ?? false) {
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
     * Create a views count cache key for static dates.
     *
     * @param  \Illuminate\Database\Eloquent\Model  $viewable
     * @param  \Carbon\Carbon|null  $sinceDateTime
     * @param  \Carbon\Carbon|null  $uptoDateTime
     * @param  bool  $unique
     * @return string
     */
    protected function createStaticDatesKey($viewable, $sinceDateTime = null, $uptoDateTime = null, bool $unique = false)
    {
        $sinceDateTimeString = $sinceDateTime ? $sinceDateTime->toDateTimeString() : '';
        $uptoDateTimeString = $uptoDateTime ? $uptoDateTime->toDateTimeString() : '';

        $requestPeriod = "{$sinceDateTimeString}|{$uptoDateTimeString}";

        return $this->createBaseDatesKey($viewable, $unique, $requestPeriod);
    }

    /**
     * Create a views count cache key for reactive dates.
     *
     * @param  \Illuminate\Database\Eloquent\Model  $viewable
     * @param  string  $pastType
     * @param  int  $pastValue
     * @return string
     */
    protected function createReactiveDatesKey($viewable, $pastType, int $pastValue, bool $unique = false)
    {
        $pastDateTime = strtolower(str_replace('_', $pastValue, $pastType));

        $requestPeriod = "{$pastDateTime}|";

        return $this->createBaseDatesKey($viewable, $unique, $requestPeriod);
    }

    /**
     * Create a views count cache key.
     *
     * @param  \Illuminate\Database\Eloquent\Model  $viewable
     * @param  bool  $unique
     * @param  string  $requestPeriod
     * @return string
     */
    public function createBaseDatesKey($viewable, bool $unique, string $requestPeriod)
    {
        $viewableId = $viewable->getKey();
        $viewableType = strtolower(str_replace('\\', '-', $viewable->getMorphClass()));

        $requestType = $unique ? 'unique' : 'normal';

        return "{$viewableType}.{$viewableId}.{$requestType}.{$requestPeriod}";
    }
}
