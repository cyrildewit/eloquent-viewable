<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable;

use Carbon\Carbon;
use Carbon\CarbonInterface;
use Closure;
use CyrildeWit\EloquentViewable\Contracts\CreateView as CreateViewContract;
use CyrildeWit\EloquentViewable\Contracts\View as ViewContract;
use CyrildeWit\EloquentViewable\Contracts\Viewable;
use CyrildeWit\EloquentViewable\Contracts\Views as ViewsContract;
use CyrildeWit\EloquentViewable\Contracts\Visitor as VisitorContract;
use CyrildeWit\EloquentViewable\Exceptions\ViewRecordException;
use CyrildeWit\EloquentViewable\Jobs\StoreView;
use CyrildeWit\EloquentViewable\Querying\Cache\CacheKey;
use CyrildeWit\EloquentViewable\Querying\Contracts\CountsViews as CountsViewsContract;
use CyrildeWit\EloquentViewable\Querying\Contracts\CountsViewsByInterval as CountsViewsByIntervalContract;
use CyrildeWit\EloquentViewable\Querying\Exceptions\InvalidInterval;
use CyrildeWit\EloquentViewable\Querying\Series\ViewSeries;
use CyrildeWit\EloquentViewable\Support\Config;
use CyrildeWit\EloquentViewable\Support\Granularity;
use CyrildeWit\EloquentViewable\Support\Period;
use CyrildeWit\EloquentViewable\Support\ViewsQuery;
use DateTimeInterface;
use Illuminate\Container\Container;
use Illuminate\Contracts\Bus\Dispatcher;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Support\Traits\Macroable;

class Views implements ViewsContract
{
    use Macroable;

    protected Viewable $viewable;

    protected ?Period $period = null;

    protected bool $unique = false;

    protected ?DateTimeInterface $cooldown = null;

    protected ?string $collection = null;

    protected ?bool $queue = null;

    protected ?DateTimeInterface $cacheLifetime = null;

    public function __construct(
        protected Config $config,
        protected CacheRepository $cache,
        protected CooldownManager $cooldownManager,
        protected VisitorContract $visitor,
        protected Dispatcher $dispatcher,
        protected CreateViewContract $createView,
        protected CountsViewsContract $countViews,
        protected CountsViewsByIntervalContract $countViewsByInterval,
    ) {}

    public function forViewable(Viewable $viewable): self
    {
        $this->viewable = $viewable;

        return $this;
    }

    public function count(): int
    {
        return $this->viaCache(null, fn (): int => $this->countViews->handle($this->viewable, $this->query()));
    }

    /**
     * @throws InvalidInterval
     */
    public function countByInterval(Granularity $granularity): ViewSeries
    {
        $period = $this->period;
        $startDateTime = $period?->getStartDateTime();

        if (! $period instanceof Period || ! $startDateTime instanceof CarbonInterface) {
            throw InvalidInterval::periodWithoutStartDateTime();
        }

        $this->guardIntervalCap($granularity, $startDateTime, $period->getEndDateTime() ?? Carbon::now());

        $counts = $this->viaCache($granularity, fn (): array => $this->countViewsByInterval->handle(
            $this->viewable,
            $this->query(),
            $granularity,
        ));

        return ViewSeries::fill($period, $granularity, $counts);
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

        $pending = $this->resolvePendingView();

        if ($this->shouldQueue()) {
            $this->dispatcher->dispatch(
                new StoreView($pending)
                    ->onConnection($this->config->queueConnection())
                    ->onQueue($this->config->queueName())
            );

            return true;
        }

        $this->createView->handle($pending);

        return true;
    }

    public function destroy(): void
    {
        Container::getInstance()
            ->make(ViewContract::class)
            ->newQueryFor($this->viewable, new ViewsQuery)
            ->delete();
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

    public function queue(bool $state = true): self
    {
        $this->queue = $state;

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

    protected function query(): ViewsQuery
    {
        return new ViewsQuery($this->period, $this->collection, $this->unique);
    }

    /**
     * Resolve a value through the cache when a lifetime is set, otherwise
     * straight from the resolver.
     *
     * @template TValue of int|array<string, int>
     *
     * @param  Closure(): TValue  $resolve
     * @return TValue
     */
    protected function viaCache(?Granularity $granularity, Closure $resolve): int|array
    {
        $cacheKey = $this->shouldCache() ? $this->makeCacheKey($granularity) : null;

        if ($cacheKey !== null) {
            /** @var TValue|null $cached */
            $cached = $this->cache->get($cacheKey);

            if ($cached !== null) {
                return $cached;
            }
        }

        $value = $resolve();

        if ($cacheKey !== null) {
            $this->cache->put($cacheKey, $value, $this->cacheLifetime);
        }

        return $value;
    }

    /**
     * @throws InvalidInterval
     */
    protected function guardIntervalCap(Granularity $granularity, CarbonInterface $startDateTime, CarbonInterface $endDateTime): void
    {
        $intervals = $granularity->countBetween($startDateTime, $endDateTime);
        $maximum = $this->config->maxIntervals();

        if ($intervals > $maximum) {
            throw InvalidInterval::producesTooManyIntervals($intervals, $maximum);
        }
    }

    protected function shouldRecord(): bool
    {
        // If ignore bots is true and the current visitor is a bot, return false
        if ($this->config->ignoreBots() && $this->visitor->isCrawler()) {
            return false;
        }

        // If we honor the DNT header and the current request contains the
        // DNT header, return false
        if ($this->config->honorDoNotTrack() && $this->visitor->hasDoNotTrackHeader()) {
            return false;
        }

        if (in_array($this->visitor->ip(), $this->config->ignoredIpAddresses(), true)) {
            return false;
        }

        return ! $this->cooldown instanceof DateTimeInterface || $this->cooldownManager->push($this->viewable, $this->cooldown, $this->collection);
    }

    protected function resolvePendingView(): PendingView
    {
        return new PendingView(
            viewableId: $this->viewable->getKey(),
            viewableType: $this->viewable->getMorphClass(),
            visitor: $this->visitor->id(),
            collection: $this->collection,
            viewedAt: Carbon::now(),
        );
    }

    protected function shouldQueue(): bool
    {
        return $this->queue ?? $this->config->queueEnabled();
    }

    protected function shouldCache(): bool
    {
        return $this->cacheLifetime instanceof DateTimeInterface;
    }

    protected function makeCacheKey(?Granularity $granularity): string
    {
        return new CacheKey(
            $this->viewable,
            $this->config->cacheKey(),
        )->make($this->query(), $granularity);
    }

    protected function resolveCacheLifetime(DateTimeInterface|int $lifetime): CarbonInterface
    {
        if (is_int($lifetime)) {
            return Carbon::now()->addMinutes($lifetime);
        }

        return Carbon::instance($lifetime);
    }
}
