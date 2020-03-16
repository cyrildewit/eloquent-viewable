<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable;

use Carbon\Carbon;
use Carbon\CarbonInterface;
use CyrildeWit\EloquentViewable\Contracts\View as ViewContract;
use CyrildeWit\EloquentViewable\Contracts\Viewable;
use CyrildeWit\EloquentViewable\Contracts\Views as ViewsContract;
use CyrildeWit\EloquentViewable\Contracts\Visitor as VisitorContract;
use CyrildeWit\EloquentViewable\Support\Period;
use DateTime;
use DateTimeInterface;
use Illuminate\Container\Container;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Traits\Macroable;
use InvalidArgumentException;

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
     * The period that the current query should be scoped to.
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
     * @var \DateTimeInterface|null
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
     * @var bool
     */
    protected $shouldCache = false;

    /**
     * Cache lifetime.
     *
     * @var \DateTimeInterface
     */
    protected $cacheLifetime;

    /**
     * The visitor instance.
     *
     * @var \CyrildeWit\EloquentViewable\Contracts\Visitor
     */
    protected $visitor;

    /**
     * The cooldown manager instance.
     *
     * @var \CyrildeWit\EloquentViewable\CooldownManager
     */
    protected $cooldownManager;

    /**
     * The config repository instance.
     *
     * @var \Illuminate\Contracts\Config\Repository
     */
    protected $config;

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
        ConfigRepository $config,
        CacheRepository $cache,
        CooldownManager $cooldownManager,
        VisitorContract $visitor
    ) {
        $this->config = $config;
        $this->cache = $cache;
        $this->cooldownManager = $cooldownManager;
        $this->cacheLifetime = Carbon::now()->addMinutes($config['eloquent-viewable']['cache']['lifetime_in_minutes']);
        $this->visitor = $visitor;
    }

    /**
     * Set the viewable model.
     *
     * @param  \CyrildeWit\EloquentViewable\Contracts\Viewable|null
     * @return $this
     */
    public function forViewable(Viewable $viewable = null): ViewsContract
    {
        $this->viewable = $viewable;

        return $this;
    }

    /**
     * Get the views count.
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

        if ($this->period) {
            $query->withinPeriod($this->period);
        }

        $query->collection($this->collection);

        $viewsCount = $this->unique ? $query->count(DB::raw('DISTINCT visitor')) : $query->count();

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

        $view = Container::getInstance()->make(ViewContract::class);
        $view->viewable_id = $this->viewable->getKey();
        $view->viewable_type = $this->viewable->getMorphClass();
        $view->visitor = $this->visitor->id();
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
     * Set the cooldown.
     *
     * @param  \DateTime|int  $cooldown
     * @return $this
     */
    public function cooldown($cooldown): ViewsContract
    {
        if (is_int($cooldown)) {
            $cooldown = Carbon::now()->addMinutes($cooldown);
        }

        if ($cooldown instanceof DateTime) {
            $cooldown = Carbon::instance($cooldown);
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
     * Set the visitor.
     *
     * @param  \CyrildeWit\EloquentViewable\Contracts\Visitor
     */
    public function useVisitor(VisitorContract $visitor)
    {
        $this->visitor = $visitor;

        return $this;
    }

    /**
     * Determine if we should record the view.
     *
     * @return bool
     */
    protected function shouldRecord(): bool
    {
        // If ignore bots is true and the current visitor is a bot, return false
        if ($this->config->get('eloquent-viewable.ignore_bots') && $this->visitor->isCrawler()) {
            return false;
        }

        // If we honor to the DNT header and the current request contains the
        // DNT header, return false
        if ($this->config->get('eloquent-viewable.honor_dnt', false) && $this->visitor->hasDoNotTrackHeader()) {
            return false;
        }

        if (collect($this->config->get('eloquent-viewable.ignored_ip_addresses'))->contains($this->visitor->ip())) {
            return false;
        }

        if ($this->cooldown !== null && ! $this->cooldownManager->push($this->viewable, $this->cooldown, $this->collection)) {
            return false;
        }

        return true;
    }

    /**
     * Determine if we should cache the views count.
     *
     * @return bool
     */
    protected function shouldCache(): bool
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

            return Container::getInstance()
                ->make(ViewContract::class)
                ->where('viewable_type', $viewableType);
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
     * Resolve cache lifetime.
     *
     * @param  \Carbon\CarbonInterface|\DateTimeInterface|int
     * @return \Carbon\CarbonInterface
     */
    protected function resolveCacheLifetime($lifetime): DateTimeInterface
    {
        if (is_int($lifetime)) {
            return Carbon::now()->addMinutes($lifetime);
        }

        if ($lifetime instanceof DateTimeInterface) {
            return Carbon::instance($lifetime);
        }

        if ($lifetime instanceof CarbonInterface) {
            return $lifetime;
        }

        throw new InvalidArgumentException("Argument $lifetime must be of type int, \Carbon\CarbonInterface or \DateTimeInterface");
    }
}
