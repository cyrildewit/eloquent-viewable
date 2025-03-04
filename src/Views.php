<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable;

use Carbon\Carbon;
use Carbon\CarbonInterface;
use CyrildeWit\EloquentViewable\Contracts\View as ViewContract;
use CyrildeWit\EloquentViewable\Contracts\Viewable;
use CyrildeWit\EloquentViewable\Contracts\Views as ViewsContract;
use CyrildeWit\EloquentViewable\Contracts\Visitor as VisitorContract;
use CyrildeWit\EloquentViewable\Events\ViewRecorded;
use CyrildeWit\EloquentViewable\Exceptions\ViewRecordException;
use CyrildeWit\EloquentViewable\Support\Period;
use DateTimeInterface;
use Illuminate\Container\Container;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Traits\Macroable;

class Views implements ViewsContract
{
    use Macroable;

    protected Viewable $viewable;

    protected ?Period $period = null;

    protected bool $unique = false;

    protected ?DateTimeInterface $cooldown = null;

    protected ?string $collection = null;

    protected ?DateTimeInterface $cacheLifetime = null;

    protected VisitorContract $visitor;

    protected CooldownManager $cooldownManager;

    protected ConfigRepository $config;

    protected CacheRepository $cache;

    public function __construct(
        ConfigRepository $config,
        CacheRepository $cache,
        CooldownManager $cooldownManager,
        VisitorContract $visitor
    ) {
        $this->config = $config;
        $this->cache = $cache;
        $this->cooldownManager = $cooldownManager;
        $this->visitor = $visitor;
    }

    public function forViewable(Viewable $viewable): self
    {
        $this->viewable = $viewable;

        return $this;
    }

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

        $query->when($this->period, function ($query, $period) {
            $query->withinPeriod($period);
        });

        $query->when($this->collection, function ($query, $collection) {
            $query->collection($collection);
        });

        $viewsCount = $this->unique ? $query->count(DB::raw('DISTINCT visitor')) : $query->count();

        if ($this->shouldCache() && $this->cacheLifetime !== null) {
            $this->cache->put($cacheKey, $viewsCount, $this->cacheLifetime);
        }

        return $viewsCount;
    }

    /**
     * @throws ViewRecordException
     */
    public function record(): bool
    {
        if ($this->viewable instanceof Viewable && $this->viewable->getKey() === null) {
            throw ViewRecordException::cannotRecordViewForViewableType();
        }

        if (! $this->shouldRecord()) {
            return false;
        }

        event(new ViewRecorded($view = $this->createView()));

        return $view->exists;
    }

    public function destroy(): void
    {
        $this->resolveViewableQuery()->delete();
    }

    public function cooldown(DateTimeInterface|int|null $cooldown): self
    {
        if (is_int($cooldown)) {
            $cooldown = Carbon::now()->addMinutes($cooldown);
        }

        if ($cooldown instanceof DateTimeInterface) {
            $cooldown = Carbon::instance($cooldown);
        }

        $this->cooldown = $cooldown;

        return $this;
    }

    public function period(?Period $period): self
    {
        $this->period = $period;

        return $this;
    }

    public function collection(?string $name): self
    {
        $this->collection = $name;

        return $this;
    }

    public function unique(bool $state = true): ViewsContract
    {
        $this->unique = $state;

        return $this;
    }

    public function remember($lifetime = null): ViewsContract
    {
        if ($lifetime !== null) {
            $lifetime = $this->resolveCacheLifetime($lifetime);
        }

        $this->cacheLifetime = $lifetime;

        return $this;
    }

    public function useVisitor(VisitorContract $visitor): ViewsContract
    {
        $this->visitor = $visitor;

        return $this;
    }

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

    protected function createView(): ViewContract
    {
        $view = Container::getInstance()->make(ViewContract::class);

        return $view->create([
            'viewable_id' => $this->viewable->getKey(),
            'viewable_type' => $this->viewable->getMorphClass(),
            'visitor' => $this->visitor->id(),
            'collection' => $this->collection,
            'viewed_at' => Carbon::now(),
        ]);
    }

    protected function shouldCache(): bool
    {
        return $this->cacheLifetime !== null;
    }

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

    protected function makeCacheKey(?Period $period = null, bool $unique = false, ?string $collection = null): string
    {
        return CacheKey::fromViewable($this->viewable)->make($period, $unique, $collection);
    }

    protected function resolveCacheLifetime(DateTimeInterface|int $lifetime): CarbonInterface
    {
        if (is_int($lifetime)) {
            return Carbon::now()->addMinutes($lifetime);
        }

        return Carbon::instance($lifetime);

    }
}
