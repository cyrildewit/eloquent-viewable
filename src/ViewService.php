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

use Cookie;
use Request;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Database\Eloquent\Builder;
use CyrildeWit\EloquentViewable\Support\Key;
use CyrildeWit\EloquentViewable\Support\Period;
use CyrildeWit\EloquentViewable\Contracts\CrawlerDetector;
use CyrildeWit\EloquentViewable\Resolvers\IpAddressResolver;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use CyrildeWit\EloquentViewable\Contracts\View as ViewContract;
use CyrildeWit\EloquentViewable\Contracts\ViewService as ViewServiceContract;

/**
 * Class ViewService.
 *
 * @author Cyril de Wit <github@cyrildewit.nl>
 */
class ViewService implements ViewServiceContract
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
     * IpAddressResolver instance.
     *
     * @var \CyrildeWit\EloquentViewable\Resolvers\IpAddressResolver
     */
    protected $ipAddressResolver;

    /**
     * The view session history instance.
     *
     * @var \CyrildeWit\EloquentViewable\ViewSessionHistory
     */
    protected $viewSessionHistory;

    /**
     * Create a new ViewService instance.
     *
     * @return void
     */
    public function __construct(IpAddressResolver $ipAddressResolver)
    {
        $this->cache = app(CacheRepository::class);
        $this->crawlerDetector = app(CrawlerDetector::class);
        $this->ipAddressResolver = $ipAddressResolver;
        $this->viewSessionHistory = app(ViewSessionHistory::class);
    }

    // /**
    //  * Get the views count based upon the given arguments.
    //  *
    //  * @param  \Illuminate\Database\Eloquent\Model  $subject
    //  * @param  \DateTime  $sinceDateTime
    //  * @param  \DateTime  $uptoDateTime
    //  * @param  bool  $unique
    //  * @return int
    //  */
    // public function getViewsCount($subject, $period = null, bool $unique = false, $tag = null)
    // {
    //     // Retrieve configuration
    //     $cachingEnabled = config('eloquent-viewable.cache.enabled', true);
    //     $cachingViewsCountEnabled = config('eloquent-viewable.cache.cache_views_count.enabled', true);

    //     // Use inserted period, otherwise create an empty one
    //     $period = $period ?? Period::create();

    //     // Make a unique key for caching
    //     $cacheKey = Key::createForCache($subject, $period, $unique);

    //     // Check cache if wanted
    //     if ($cachingEnabled && $cachingViewsCountEnabled) {
    //         $cachedViewsCount = $this->cache->get($cacheKey);

    //         if ($cachedViewsCount !== null) {
    //             return (int) $cachedViewsCount;
    //         }
    //     }

    //     // Count the views again
    //     $viewsCount = $this->countViews($subject, $period->getStartDateTime(), $period->getEndDateTime(), $unique);

    //     // Cache the counted views
    //     if ($cachingEnabled) {
    //         $lifetime = config('eloquent-viewable.cache.cache_views_count.lifetime_in_minutes', 60);
    //         $this->cache->put($cacheKey, $viewsCount, $lifetime);
    //     }

    //     return $viewsCount;
    // }

    // /**
    //  * Get the unique views count based upon the given arguments.
    //  *
    //  * @param  \Illuminate\Database\Eloquent\Model  $viewable
    //  * @param  \DateTime|null  $sinceDateTime
    //  * @param  \DateTime|null  $uptoDateTime
    //  * @return int
    //  */
    // public function getUniqueViewsCount($viewable, $period = null): int
    // {
    //     return $this->getViewsCount($viewable, $period, true);
    // }

    /**
     * Count the views based upon the given arguments.
     *
     * @param  \Illuminate\Database\Eloquent\Model  $viewable
     * @param  \DateTime  $startDateTime
     * @param  \DateTime  $endDateTime
     * @param  bool  $unique
     * @return int
     */
    // public function countViews($viewable, $startDateTime = null, $endDateTime = null, bool $unique = false): int
    // {
    //     // Create new Query Builder instance of the views relationship
    //     $query = $viewable->views();

    //     // Apply the following date filters
    //     if ($startDateTime && ! $endDateTime) {
    //         $query->where('viewed_at', '>=', $startDateTime);
    //     } elseif (! $startDateTime && $endDateTime) {
    //         $query->where('viewed_at', '<=', $endDateTime);
    //     } elseif ($startDateTime && $endDateTime) {
    //         $query->whereBetween('viewed_at', [$startDateTime, $endDateTime]);
    //     }

    //     // Count all the views
    //     if (! $unique) {
    //         $viewsCount = $query->count();
    //     }

    //     // Count only the unique views
    //     if ($unique) {
    //         $viewsCount = $query->distinct('visitor')->count('visitor');
    //     }

    //     return $viewsCount;
    // }

    // /**
    //  * Store a new view.
    //  *
    //  * @param  \Illuminate\Database\Eloquent\Model  $viewable
    //  * @return bool
    //  */
    // public function addViewTo($viewable, $tag = null): bool
    // {
    //     $ignoreBots = config('eloquent-viewable.ignore_bots', true);
    //     $honorToDnt = config('eloquent-viewable.honor_dnt', false);
    //     $cookieName = config('eloquent-viewable.visitor_cookie_key', 'eloquent_viewable');

    //     // If ignore bots is true and the current viewer is a bot, return false
    //     if ($ignoreBots && $this->crawlerDetector->isBot()) {
    //         return false;
    //     }

    //     // If we honor to the DNT header and the current request contains the
    //     // DNT header, return false
    //     if ($honorToDnt && (Request::header('HTTP_DNT') == 1)) {
    //         return false;
    //     }

    //     $ignoredIpAddresses = Collection::make(config('eloquent-viewable.ignored_ip_addresses', []));

    //     if ($ignoredIpAddresses->contains($this->ipAddressResolver->get())) {
    //         return false;
    //     }

    //     $visitorCookie = Cookie::get($cookieName);

    //     $view = app(ViewContract::class);
    //     $view->viewable_id = $viewable->getKey();
    //     $view->viewable_type = $viewable->getMorphClass();
    //     $view->visitor = $visitorCookie;
    //     $view->tag = $tag;
    //     $view->viewed_at = Carbon::now();
    //     $view->save();

    //     return true;
    // }

    /**
     * Store a new view.
     *
     * @param  \Illuminate\Database\Eloquent\Model  $viewable
     * @param  \DateTime  $expiryDateTime
     * @return bool
     */
    public function addViewWithExpiryDateTo($viewable, $expiryDateTime)
    {
        if ($this->viewSessionHistory->push($viewable, $expiryDateTime)) {
            return $this->addViewTo($viewable);
        }

        return false;
    }

    /**
     * Remove all views of a viewable model.
     *
     * @param  \Illuminate\Database\Eloquent\Model  $viewable
     * @return void
     */
    public function deleteViewsFor($viewable)
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
     * @param  bool  $unique
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function applyScopeOrderByViewsCount(Builder $query, string $direction = 'desc', bool $unique = false): Builder
    {
        $viewable = $query->getModel();
        $viewModel = app(ViewContract::class);

        if ($unique) {
            return $query->leftJoin($viewModel->getTable(), "{$viewModel->getTable()}.viewable_id", '=', "{$viewable->getTable()}.{$viewable->getKeyName()}")
                ->selectRaw("{$viewable->getTable()}.*, count(distinct visitor) as numOfUniqueViews")
                ->groupBy("{$viewable->getTable()}.{$viewable->getKeyName()}")
                ->orderBy('numOfUniqueViews', $direction);
        }

        return $query->leftJoin($viewModel->getTable(), "{$viewModel->getTable()}.viewable_id", '=', "{$viewable->getTable()}.{$viewable->getKeyName()}")
            ->selectRaw("{$viewable->getTable()}.*, count(`{$viewModel->getTable()}`.`{$viewModel->getKeyName()}`) as numOfViews")
            ->groupBy("{$viewable->getTable()}.{$viewable->getKeyName()}")
            ->orderBy('numOfViews', $direction);
    }
}
