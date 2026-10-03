<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Querying;

use Carbon\Carbon;
use Carbon\CarbonInterface;
use Closure;
use CyrildeWit\EloquentViewable\Contracts\Viewable;
use CyrildeWit\EloquentViewable\Exceptions\InvalidPeriod;
use CyrildeWit\EloquentViewable\Querying\Cache\CacheKey;
use CyrildeWit\EloquentViewable\Querying\Comparison\ViewComparison;
use CyrildeWit\EloquentViewable\Querying\Contracts\ViewSource;
use CyrildeWit\EloquentViewable\Querying\Exceptions\InvalidInterval;
use CyrildeWit\EloquentViewable\Querying\Series\ViewSeries;
use CyrildeWit\EloquentViewable\Support\Config;
use CyrildeWit\EloquentViewable\Support\Granularity;
use CyrildeWit\EloquentViewable\Support\Period;
use CyrildeWit\EloquentViewable\Support\Timezone;
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

    /**
     * Two counts, one over the period and one over `Period::previous()`,
     * each remembered under its own key.
     *
     * @throws InvalidPeriod
     */
    public function compare(Viewable $viewable, ViewsQuery $query, ?CarbonInterface $rememberUntil = null): ViewComparison
    {
        $period = $query->period ?? throw InvalidPeriod::comparedWithoutPeriod();
        $previous = $period->previous();

        return ViewComparison::between(
            $this->count($viewable, $query, $rememberUntil),
            $this->count($viewable, $query->withPeriod($previous), $rememberUntil),
            $period,
            $previous,
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

        $timezone = $query->timezone ?? Timezone::application();

        $this->guardIntervalCap(
            $granularity,
            $startDateTime->avoidMutation()->setTimezone($timezone),
            ($period->getEndDateTime() ?? Carbon::now())->avoidMutation()->setTimezone($timezone),
        );

        $counts = $this->remember(
            $rememberUntil,
            fn (): string => new CacheKey($viewable, $this->config->cacheKey(), $this->config->sourceDriver())->make($query, $granularity),
            fn (): array => $this->source->countByInterval($viewable, $query, $granularity),
        );

        return ViewSeries::fill($period, $granularity, $counts, $timezone);
    }

    /**
     * Most viewed first, then by name, so the order is the same on every
     * driver. Sorting in SQL would place the default collection, stored as
     * null, differently per database.
     *
     * @return array<string, int>
     */
    public function countByCollection(Viewable $viewable, ViewsQuery $query, ?CarbonInterface $rememberUntil = null): array
    {
        $counts = $this->remember(
            $rememberUntil,
            fn (): string => new CacheKey($viewable, $this->config->cacheKey(), $this->config->sourceDriver())->make($query, grouping: 'collection'),
            fn (): array => $this->source->countByCollection($viewable, $query),
        );

        // Sorting is stable, so names ordered first settle the ties. Compared as
        // strings because PHP keys a numeric name such as "2024" as an integer.
        ksort($counts, SORT_STRING);
        arsort($counts);

        return $counts;
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
