<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Querying;

use Carbon\Carbon;
use Carbon\CarbonInterface;
use Closure;
use CyrildeWit\EloquentViewable\Contracts\Viewable;
use CyrildeWit\EloquentViewable\Querying\Cache\CacheKey;
use CyrildeWit\EloquentViewable\Querying\Contracts\ViewSource;
use CyrildeWit\EloquentViewable\Querying\Exceptions\InvalidInterval;
use CyrildeWit\EloquentViewable\Querying\Series\ViewSeries;
use CyrildeWit\EloquentViewable\Support\Config;
use CyrildeWit\EloquentViewable\Support\Granularity;
use CyrildeWit\EloquentViewable\Support\Period;
use CyrildeWit\EloquentViewable\Support\ViewsQuery;
use Illuminate\Contracts\Cache\Repository as CacheRepository;

final readonly class Reader
{
    public function __construct(
        private ViewSource $source,
        private CacheRepository $cache,
        private Config $config,
    ) {}

    public function count(Viewable $viewable, ViewsQuery $query, ?CarbonInterface $rememberUntil = null): int
    {
        return $this->remember(
            $rememberUntil,
            fn (): string => new CacheKey($viewable, $this->config->cacheKey(), $this->config->sourceDriver())->make($query),
            fn (): int => $this->source->count($viewable, $query),
        );
    }

    /** @throws InvalidInterval */
    public function countByInterval(Viewable $viewable, ViewsQuery $query, Granularity $granularity, ?CarbonInterface $rememberUntil = null): ViewSeries
    {
        $period = $query->period;
        $startDateTime = $period?->getStartDateTime();

        if (! $period instanceof Period || ! $startDateTime instanceof CarbonInterface) {
            throw InvalidInterval::periodWithoutStartDateTime();
        }

        $this->guardIntervalCap($granularity, $startDateTime, $period->getEndDateTime() ?? Carbon::now());

        $counts = $this->remember(
            $rememberUntil,
            fn (): string => new CacheKey($viewable, $this->config->cacheKey(), $this->config->sourceDriver())->make($query, $granularity),
            fn (): array => $this->source->countByInterval($viewable, $query, $granularity),
        );

        return ViewSeries::fill($period, $granularity, $counts);
    }

    /**
     * @template TValue of int|array<string, int>
     *
     * @param  Closure(): string  $key
     * @param  Closure(): TValue  $resolve
     * @return TValue
     */
    private function remember(?CarbonInterface $until, Closure $key, Closure $resolve): int|array
    {
        if (! $until instanceof CarbonInterface) {
            return $resolve();
        }

        $cacheKey = $key();

        /** @var TValue|null $cached */
        $cached = $this->cache->get($cacheKey);

        if ($cached !== null) {
            return $cached;
        }

        $value = $resolve();

        $this->cache->put($cacheKey, $value, $until);

        return $value;
    }

    /** @throws InvalidInterval */
    private function guardIntervalCap(Granularity $granularity, CarbonInterface $startDateTime, CarbonInterface $endDateTime): void
    {
        $intervals = $granularity->countBetween($startDateTime, $endDateTime);
        $maximum = $this->config->maxIntervals();

        if ($intervals > $maximum) {
            throw InvalidInterval::producesTooManyIntervals($intervals, $maximum);
        }
    }
}
