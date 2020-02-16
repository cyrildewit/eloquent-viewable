<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable;

use Carbon\Carbon;
use CyrildeWit\EloquentViewable\Contracts\HeaderResolver;
use CyrildeWit\EloquentViewable\Contracts\View as ViewContract;
use CyrildeWit\EloquentViewable\Contracts\Viewable as ViewableContract;
use CyrildeWit\EloquentViewable\Contracts\Views as ViewsContract;
use CyrildeWit\EloquentViewable\Support\Period;
use DateTime;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Traits\Macroable;

class Views implements ViewsContract
{
    use Macroable;

    /**
     * The viewable model where we are applying actions to.
     *
     * @var \CyrildeWit\EloquentViewable\Contracts\Viewable
     */
    protected $viewable;

    /**
     * The period that the current query should scoped to.
     *
     * @var \CyrildeWit\EloquentViewable\Support\Period|null
     */
    protected $period = null;

    /**
     * Determine if only unique views should be returned.
     *
     * @var bool
     */
    protected $unique = false;

    /**
     * The cooldown that should be over before a new view can be recorded.
     *
     * @var \DateTime|null
     */
    protected $cooldown = null;

    /**
     * The collection under where the view will be saved.
     *
     * @var string|null
     */
    protected $collection = null;

    /**
     * Determine if the views count should be cached.
     *
     * @var string|null
     */
    protected $shouldCache = false;

    /**
     * Cache lifetime.
     *
     * @var \DateTime
     */
    protected $cacheLifetime;

    /**
     * Used IP Address instead of the provided one by the resolver.
     *
     * @var string
     */
    protected $overriddenIpAddress;

    /**
     * Used visitor ID instead of the provided one by a cookie.
     *
     * @var string
     */
    protected $overriddenVisitor;

    /**
     * The viewer instance.
     *
     * @var \CyrildeWit\EloquentViewable\Viewer
     */
    protected $viewer;

    /**
     * The view session history instance.
     *
     * @var \CyrildeWit\EloquentViewable\ViewSessionHistory
     */
    protected $viewSessionHistory;

    /**
     * The request header resolver instance.
     *
     * @var \CyrildeWit\EloquentViewable\Contracts\HeaderResolver
     */
    protected $headerResolver;

    /**
     * The cache repository instance.
     *
     * @var \Illuminate\Contracts\Cache\Repository
     */
    protected $cache;

    /**
     * Create a new views instance.
     *
     * @return void
     */
    public function __construct(
        Viewer $viewer,
        ViewSessionHistory $viewSessionHistory,
        HeaderResolver $headerResolver,
        CacheRepository $cache
    ) {
        $this->viewer = $viewer;
        $this->viewSessionHistory = $viewSessionHistory;
        $this->headerResolver = $headerResolver;
        $this->cache = $cache;
        $this->cacheLifetime = Carbon::now()->addMinutes(config('eloquent-viewable.cache.lifetime_in_minutes'));
    }

    /**
     * Set the viewable model.
     *
     * @param  \CyrildeWit\EloquentViewable\Contracts\Viewable|null
     * @return $this
     */
    public function forViewable(ViewableContract $viewable = null): ViewsContract
    {
        $this->viewable = $viewable;

        return $this;
    }

    /**
     * Count the views.
     *
     * @return int
     */
    public function count(): int
    {
        $query = $this->resolveViewableQuery();

        $cacheKey = $this->makeCacheKey($this->period, $this->unique, $this->collection);

        if ($this->shouldCache()) {
            $cachedViewsCount = $this->cache->get($cacheKey);

            // Return cached views count if it exists
            if ($cachedViewsCount !== null) {
                return (int) $cachedViewsCount;
            }
        }

        if ($period = $this->period) {
            $query->withinPeriod($period);
        }

        $query->collection($this->collection);

        if ($this->unique) {
            $viewsCount = $query->uniqueVisitor()->count('visitor');
        } else {
            $viewsCount = $query->count();
        }

        if ($this->shouldCache()) {
            $this->cache->put($cacheKey, $viewsCount, $this->cacheLifetime);
        }

        return $viewsCount;
    }

    /**
     * Record a view.
     *
     * @return \CyrildeWit\EloquentViewable\Contracts\View|void
     */
    public function record()
    {
        if (! $this->shouldRecord()) {
            return;
        }

        $view = app(ViewContract::class);
        $view->viewable_id = $this->viewable->getKey();
        $view->viewable_type = $this->viewable->getMorphClass();
        $view->visitor = $this->resolveVisitorId();
        $view->collection = $this->collection;
        $view->viewed_at = Carbon::now();
        $view->save();

        return $view;
    }

    /**
     * Destroy all views of the viewable model.
     *
     * @return void
     */
    public function destroy()
    {
        $this->resolveViewableQuery()->delete();
    }

    /**
     * Set a cooldown.
     *
     * @param  \DateTime|int  $cooldown
     * @return $this
     */
    public function cooldown($cooldown): ViewsContract
    {
        if (is_int($cooldown)) {
            $cooldown = Carbon::now()->addMinutes($cooldown);
        }

        $this->cooldown = $cooldown;

        return $this;
    }

    /**
     * Set the period.
     *
     * @param  \CyrildeWit\EloquentViewable\Period
     * @return $this
     */
    public function period($period): ViewsContract
    {
        $this->period = $period;

        return $this;
    }

    /**
     * Set the collection.
     *
     * @param  string
     * @return $this
     */
    public function collection(string $name): ViewsContract
    {
        $this->collection = $name;

        return $this;
    }

    /**
     * Fetch only unique views.
     *
     * @param  bool  $state
     * @return $this
     */
    public function unique(bool $state = true): ViewsContract
    {
        $this->unique = $state;

        return $this;
    }

    /**
     * Cache the current views count.
     *
     * @param  \DateTime|int|null  $lifetime
     * @return $this
     */
    public function remember($lifetime = null): ViewsContract
    {
        $this->shouldCache = true;

        // Make sure something other than the default value (null) is given.
        // Then resolve the DateTime instance from the given value.
        if ($lifetime !== null) {
            $this->cacheLifetime = $this->resolveCacheLifetime($lifetime);
        }

        return $this;
    }

    /**
     * Override the visitor's IP Address.
     *
     * @param  string  $address
     * @return $this
     */
    public function useIpAddress(string $address): ViewsContract
    {
        $this->overriddenIpAddress = $address;

        return $this;
    }

    /**
     * Override the visitor's unique ID.
     *
     * @param  string  $visitor
     * @return $this
     */
    public function useVisitor(string $visitor): ViewsContract
    {
        $this->overriddenVisitor = $visitor;

        return $this;
    }

    /**
     * Determine if we should record the view.
     *
     * @return bool
     */
    protected function shouldRecord(): bool
    {
        // If ignore bots is true and the current viewer is a bot, return false
        if (config('eloquent-viewable.ignore_bots') && $this->viewer->isCrawler()) {
            return false;
        }

        // If we honor to the DNT header and the current request contains the
        // DNT header, return false
        if (config('eloquent-viewable.honor_dnt', false) && $this->viewer->hasDoNotTrackHeader()) {
            return false;
        }

        if (collect(config('eloquent-viewable.ignored_ip_addresses'))->contains($this->resolveIpAddress())) {
            return false;
        }

        if ($this->cooldown !== null && ! $this->viewSessionHistory->push($this->viewable, $this->cooldown, $this->collection)) {
            return false;
        }

        return true;
    }

    /**
     * Determine if we should cache the views count.
     *
     * @return bool
     */
    protected function shouldCache()
    {
        return $this->shouldCache;
    }

    /**
     * Resolve the viewable query builder instance.
     *
     * @return \Illuminate\Database\Eloquent\Builder
     */
    protected function resolveViewableQuery(): Builder
    {
        // If null, we take for granted that we need to count the viewable type
        if ($this->viewable->getKey() === null) {
            $viewableType = $this->viewable->getMorphClass();

            return app(ViewContract::class)->where('viewable_type', $viewableType);
        }

        return $this->viewable->views()->getQuery();
    }

    /**
     * Make a cache key for the viewable with custom query options.
     *
     * @param  \CyrildeWit\EloquentViewable\Support\Period|null  $period
     * @param  bool  $unique
     * @param  string|null  $collection
     * @return string
     */
    protected function makeCacheKey($period = null, bool $unique = false, string $collection = null): string
    {
        return (CacheKey::fromViewable($this->viewable))->make($period, $unique, $collection);
    }

    /**
     * Resolve the visitor's IP Address.
     *
     * It will first check if the overriddenIpAddress property has been set,
     * otherwise it will resolve it using the IP Address resolver.
     *
     * @return string
     */
    protected function resolveIpAddress(): string
    {
        return $this->overriddenIpAddress ?? $this->viewer->ip();
    }

    /**
     * Resolve the visitor's unique ID.
     *
     * @return string|null
     */
    protected function resolveVisitorId()
    {
        return $this->overriddenVisitor ?? $this->viewer->id();
    }

    /**
     * Resolve cache lifetime.
     *
     * @param  DateTime|int
     * @return \Carbon\Carbon
     */
    protected function resolveCacheLifetime($lifetime): DateTime
    {
        if ($lifetime instanceof DateTime) {
            return $lifetime;
        }

        if (is_int($lifetime)) {
            return Carbon::now()->addMinutes($lifetime);
        }
    }
}
