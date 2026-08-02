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
use Illuminate\Database\Eloquent\Model;
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

    public function __construct(protected ConfigRepository $config, protected CacheRepository $cache, protected CooldownManager $cooldownManager, protected VisitorContract $visitor) {}

    public function forViewable(Viewable $viewable): self
    {
        $this->viewable = $viewable;

        return $this;
    }

    public function count(): int
    {
        $cacheKey = $this->shouldCache()
            ? $this->makeCacheKey($this->period, $this->unique, $this->collection)
            : null;

        if ($cacheKey !== null) {
            $cachedViewsCount = $this->cache->get($cacheKey);

            // Return cached views count if it exists
            if ($cachedViewsCount !== null) {
                return (int) $cachedViewsCount;
            }
        }

        $viewsCount = $this->queryViewsCount();

        if ($cacheKey !== null) {
            $this->cache->put($cacheKey, $viewsCount, $this->cacheLifetime);
        }

        return $viewsCount;
    }

    protected function queryViewsCount(): int
    {
        $query = $this->resolveViewableQuery();

        if ($this->period !== null) {
            $query->withinPeriod($this->period);
        }

        if ($this->collection !== null) {
            $query->collection($this->collection);
        }

        return $this->unique ? $query->distinct()->count('visitor') : $query->count();
    }

    /**
     * @throws ViewRecordException
     */
    public function record(): bool
    {
        if ($this->viewable->getKey() === null) {
            throw ViewRecordException::cannotRecordViewForViewableType();
        }

        if (! $this->shouldRecord()) {
            return false;
        }

        event(new ViewRecorded($this->createView()));

        return true;
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

    public function remember(DateTimeInterface|int|null $lifetime = null): ViewsContract
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

        // If we honor the DNT header and the current request contains the
        // DNT header, return false
        if ($this->config->get('eloquent-viewable.honor_dnt', false) && $this->visitor->hasDoNotTrackHeader()) {
            return false;
        }

        if (collect((array) $this->config->get('eloquent-viewable.ignored_ip_addresses'))->contains($this->visitor->ip())) {
            return false;
        }

        return ! $this->cooldown instanceof DateTimeInterface || $this->cooldownManager->push($this->viewable, $this->cooldown, $this->collection);
    }

    protected function createView(): ViewContract
    {
        /** @var ViewContract $view */
        $view = Container::getInstance()->make(ViewContract::class)->create([
            'viewable_id' => $this->viewable->getKey(),
            'viewable_type' => $this->viewable->getMorphClass(),
            'visitor' => $this->visitor->id(),
            'collection' => $this->collection,
            'viewed_at' => Carbon::now(),
        ]);

        return $view;
    }

    protected function shouldCache(): bool
    {
        return $this->cacheLifetime instanceof DateTimeInterface;
    }

    /**
     * @return Builder<Model>
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

    protected function makeCacheKey(?Period $period = null, bool $unique = false, ?string $collection = null): string
    {
        return new CacheKey(
            $this->viewable,
            (string) $this->config->get('eloquent-viewable.cache.key'),
        )->make($period, $unique, $collection);
    }

    protected function resolveCacheLifetime(DateTimeInterface|int $lifetime): CarbonInterface
    {
        if (is_int($lifetime)) {
            return Carbon::now()->addMinutes($lifetime);
        }

        return Carbon::instance($lifetime);

    }
}
