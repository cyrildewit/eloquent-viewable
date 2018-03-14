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
use Jaybizzle\CrawlerDetect\CrawlerDetect;
use CyrildeWit\EloquentViewable\Enums\PastType;
use CyrildeWit\EloquentViewable\Jobs\ProcessView;
use CyrildeWit\EloquentViewable\Cache\ViewsCountCacheRepository;
use CyrildeWit\EloquentViewable\Contracts\Models\View as ViewContract;

/**
 * Class ViewableService.
 *
 * @author Cyril de Wit <github@cyrildewit.nl>
 */
class ViewableService
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
     * @param  \Illuminate\Database\Eloquent\Model  $model
     * @param  \Carbon\Carbon|null  $sinceDateTime
     * @param  \Carbon\Carbon|null  $uptoDateTime
     * @param  bool  $unique
     * @return int
     */
    public function getViewsCount($model, $sinceDateTime = null, $uptoDateTime = null, bool $unique = false): int
    {
        // Key based upon the arguments to retrieve cached views counts
        $viewsCountKey = $this->createStaticDatesKey($model, $sinceDateTime, $uptoDateTime, $unique);

        $cachingEnabled = config('eloquent-viewable.cache.cache_views_count.enabled', true);

        if ($cachingEnabled) {
            if (! is_null($cachedViewsCount = $this->viewsCountCacheRepository->get($viewsCountKey))) {
                return $cachedViewsCount;
            }
        }

        // Count the views again
        $viewsCount = $this->countViews($model, $sinceDateTime, $uptoDateTime, $unique);

        // Cache the counted views
        if ($cachingEnabled) {
            $this->viewsCountCacheRepository->put($viewsCountKey, $viewsCount);
        }

        return $viewsCount;
    }

    /**
     * Get the unique views count based upon the given arguments.
     *
     * @param  \Illuminate\Database\Eloquent\Model  $model
     * @param  \Carbon\Carbon|null  $sinceDateTime
     * @param  \Carbon\Carbon|null  $uptoDateTime
     * @param  bool  $unique
     * @return int
     */
    public function getUniqueViewsCount($model, $sinceDateTime = null, $uptoDateTime = null): int
    {
        return $this->getViewsCount($model, $sinceDateTime, $uptoDateTime, true);
    }

    /**
     * Get the views count of the past period.
     *
     * @param  \Illuminate\Database\Eloquent\Model  $model
     * @param  string  $pastType
     * @param  int  $pastValue
     * @param  bool  $unique
     * @return int
     */
    public function getViewsCountOfPast($model, $pastType, int $pastValue, bool $unique = false): int
    {
        $now = Carbon::now();

        if ($pastType == PastType::PAST_SECONDS) {
            $sinceDateTime = $now->copy()->subSeconds($pastValue);
        }

        if ($pastType == PastType::PAST_MINUTES) {
            $sinceDateTime = $now->copy()->subMinutes($pastValue);
        }

        if ($pastType == PastType::PAST_DAYS) {
            $sinceDateTime = $now->copy()->subDays($pastValue);
        }

        if ($pastType == PastType::PAST_WEEKS) {
            $sinceDateTime = $now->copy()->subWeeks($pastValue);
        }

        if ($pastType == PastType::PAST_MONTHS) {
            $sinceDateTime = $now->copy()->subMonths($pastValue);
        }

        if ($pastType == PastType::PAST_YEARS) {
            $sinceDateTime = $now->copy()->subYears($pastValue);
        }

        // Key based upon the arguments to retrieve cached views counts
        $viewsCountKey = $this->createReactiveDatesKey($model, $pastType, $pastValue, $unique);

        $cachingEnabled = config('eloquent-viewable.cache.cache_views_count.enabled', true);

        if ($cachingEnabled) {
            if (! is_null($cachedViewsCount = $this->viewsCountCacheRepository->get($viewsCountKey))) {
                return $cachedViewsCount;
            }
        }

        // Count the views again
        $viewsCount = $this->countViews($model, $sinceDateTime, $uptoDateTime, $unique);

        // Cache the counted views
        if ($cachingEnabled) {
            $this->viewsCountCacheRepository->put($viewsCountKey, $viewsCount);
        }

        return $viewsCount;
    }

    /**
     * Get the unique views count of the past period.
     *
     * @param  \Illuminate\Database\Eloquent\Model  $pastType
     * @param  int  $subValue
     * @param  bool  $unique
     * @return int
     */
    public function getUniqueViewsCountOfPast($model, $pastType, int $pastValue)
    {
        return $this->getViewsCountOfPast($model, $pastValue, true);
    }

    /**
     * Count the views based upon the given arguments.
     *
     * @param  \Illuminate\Database\Eloquent\Model  $model
     * @param  \Carbon\Carbon  $sinceDateTime
     * @param  \Carbon\Carbon  $uptoDateTime
     * @param  bool  $unique
     * @return int
     */
    public function countViews($model, $sinceDateTime = null, $uptoDateTime = null, bool $unique = false): int
    {
        // Create new Query Builder instance of the views relationship
        $query = $model->views();

        // Apply the following date filters
        if ($sinceDateTime && ! $uptoDateTime) {
            $query->where('viewed_at', '>', $sinceDateTime);
        } elseif (! $sinceDateTime && $uptoDateTime) {
            $query->where('viewed_at', '<', $uptoDateTime);
        } elseif ($sinceDateTime && $uptoDateTime) {
            $query->whereBetween('viewed_at', [$sinceDateTime, $uptoDateTime]);
        }

        // Retrieve a collection of all the ip addresses and group them by
        // ip address
        if ($unique) {
            $query->select('cookie_value')->groupBy('cookie_value');
        }

        // If the unique option is false then just use the SQL count method,
        // otherwise get the results and count them
        $viewsCount = ! $unique ? $query->count() : $query->get()->count();

        return $viewsCount;
    }

    /**
     * Store a new view.
     *
     * @param  \Illuminate\Database\Eloquent\Model  $model
     * @return bool
     */
    public function addViewTo($model): bool
    {
        $ignoreBots = config('eloquent-viewable.ignore_bots', true);
        $honorToDnt = config('eloquent-viewable.honor_dnt', false);
        $cookieName = config('eloquent-viewable.cookie_name', 'ELOQUENT_VIEWABLE_COOKIE');

        // If ignore bots is true and the current viewer is a bot, return false
        if ($ignoreBots && $this->crawlerDetect->isCrawler()) {
            return false;
        }

        // If we honor to the DNT header and the current request contains the
        // DNT header, return false
        if ($honorToDnt && $this->requestHasDntHeader()) {
            return false;
        }

        // If there is a cookie, get it, otherwise create new one
        if (Cookie::has($cookieName)) {
            $cookieValue = Cookie::get($cookieName);
        } else {
            $cookieValue = Cookie::forever($cookieName, str_random(80));
        }

        // Create a new View model instance
        $view = app(ViewContract::class)->create([
            'viewable_id' => $model->getKey(),
            'viewable_type' => get_class($model),
            'cookie_value' => $cookieValue,
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
     * Check if the current request contains the HTTP_DNT header and check if
     * it's true.
     *
     * @return bool
     */
    protected function requestHasDntHeader()
    {
        return Request::header('HTTP_DNT') == 1;
    }

    /**
     * Create a views count cache key for static dates.
     *
     * @param  \Illuminate\Database\Eloquent\Model  $model
     * @param  \Carbon\Carbon|null  $sinceDateTime
     * @param  \Carbon\Carbon|null  $uptoDateTime
     * @param  bool  $unique
     * @return string
     */
    protected function createStaticDatesKey($model, $sinceDateTime = null, $uptoDateTime = null, bool $unique = false)
    {
        $sinceDateTimeString = $sinceDateTime ? $sinceDateTime->toDateTimeString() : '';
        $uptoDateTimeString = $uptoDateTime ? $uptoDateTime->toDateTimeString() : '';

        $requestPeriod = "{$sinceDateTimeString}|{$uptoDateTimeString}";

        return $this->createBaseDatesKey($model, $unique, $requestPeriod);
    }

    /**
     * Create a views count cache key for reactive dates.
     *
     * @param  \Illuminate\Database\Eloquent\Model  $model
     * @param  string  $pastType
     * @param  int  $pastValue
     * @return string
     */
    protected function createReactiveDatesKey($model, $pastType, int $pastValue, bool $unique = false)
    {
        $pastDateTime = strtolower(str_replace('_', $pastValue, $pastType));

        $requestPeriod = "{$pastDateTime}|";

        return $this->createBaseDatesKey($model, $unique, $requestPeriod);
    }

    /**
     * Create a views count cache key.
     *
     * @param  \Illuminate\Database\Eloquent\Model  $model
     * @param  bool  $unique
     * @param  string  $requestPeriod
     * @return string
     */
    public function createBaseDatesKey($model, bool $unique, string $requestPeriod)
    {
        $modelId = $model->getKey();
        $modelType = strtolower(str_replace('\\', '-', get_class($model)));

        $requestType = $unique ? 'unique' : 'normal';

        return "{$modelType}.{$modelId}.{$requestType}.{$requestPeriod}";
    }
}
