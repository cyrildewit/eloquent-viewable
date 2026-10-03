<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Querying;

use Carbon\Carbon;
use Carbon\CarbonInterface;
use Closure;
use CyrildeWit\EloquentViewable\Contracts\Viewable;
use CyrildeWit\EloquentViewable\Exceptions\InvalidPeriod;
use CyrildeWit\EloquentViewable\Exceptions\InvalidViewable;
use CyrildeWit\EloquentViewable\Models\View;
use CyrildeWit\EloquentViewable\Querying\Cache\CacheKey;
use CyrildeWit\EloquentViewable\Querying\Comparison\ViewComparison;
use CyrildeWit\EloquentViewable\Querying\Contracts\ViewSource;
use CyrildeWit\EloquentViewable\Querying\Exceptions\InvalidInterval;
use CyrildeWit\EloquentViewable\Querying\Exceptions\InvalidLimit;
use CyrildeWit\EloquentViewable\Querying\Ranking\Ranking;
use CyrildeWit\EloquentViewable\Querying\Ranking\ViewableLoader;
use CyrildeWit\EloquentViewable\Querying\Series\ViewSeries;
use CyrildeWit\EloquentViewable\Support\Config;
use CyrildeWit\EloquentViewable\Support\Granularity;
use CyrildeWit\EloquentViewable\Support\Period;
use CyrildeWit\EloquentViewable\Support\Timezone;
use CyrildeWit\EloquentViewable\Support\ViewableKey;
use CyrildeWit\EloquentViewable\Support\ViewableSet;
use CyrildeWit\EloquentViewable\Support\ViewsQuery;
use Illuminate\Contracts\Cache\Repository as CacheRepository;

final readonly class Reader
{
    public function __construct(
        private ViewSource $source,
        private CacheRepository $cache,
        private Config $config,
        private View $view,
        private ViewableLoader $loader,
    ) {}

    public function count(Viewable $viewable, ViewsQuery $query, ?CarbonInterface $rememberUntil = null): int
    {
        return $this->remember(
            $rememberUntil,
            fn (): string => $this->cacheKey($viewable)->make($query),
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

    /**
     * Every viewable of the set, in the order given, keyed by its key. A
     * remembered count shares its entry with `count()` for that viewable, so
     * only the viewables missing from the cache reach the source.
     *
     * @return array<int|string, int>
     */
    public function countMany(ViewableSet $viewables, ViewsQuery $query, ?CarbonInterface $rememberUntil = null): array
    {
        $type = $viewables->type();

        if (! $type instanceof Viewable) {
            return [];
        }

        $cacheKeys = $rememberUntil instanceof CarbonInterface ? $this->cacheKeys($viewables, $query) : [];
        $counts = $this->cached($cacheKeys);
        $missing = array_values(array_filter($viewables->keys(), fn (int|string $key): bool => ! isset($counts[$key])));

        if ($missing !== []) {
            $fetched = $this->source->countMany($type, $missing, $query);
            $fresh = [];

            foreach ($missing as $key) {
                $fresh[$key] = $fetched[$key] ?? 0;
            }

            if ($rememberUntil instanceof CarbonInterface) {
                $this->cacheMany($cacheKeys, $fresh, $rememberUntil);
            }

            $counts += $fresh;
        }

        return array_replace(array_fill_keys(array_keys($viewables->all()), 0), $counts);
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
            fn (): string => $this->cacheKey($viewable)->make($query, $granularity),
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
            fn (): string => $this->cacheKey($viewable)->make($query, grouping: 'collection'),
            fn (): array => $this->source->countByCollection($viewable, $query),
        );

        // Sorting is stable, so names ordered first settle the ties. Compared as
        // strings because PHP keys a numeric name such as "2024" as an integer.
        ksort($counts, SORT_STRING);
        arsort($counts);

        return $counts;
    }

    /**
     * @throws InvalidLimit
     * @throws InvalidViewable
     */
    public function top(?Viewable $viewable, ViewsQuery $query, int $limit, ?CarbonInterface $rememberUntil = null): Ranking
    {
        if ($limit < 1) {
            throw InvalidLimit::belowOne($limit);
        }

        if ($viewable instanceof Viewable && ViewableKey::of($viewable) !== null) {
            throw InvalidViewable::cannotRankOne($viewable);
        }

        $rows = $this->remember(
            $rememberUntil,
            fn (): string => $this->cacheKey($viewable)->make($query, limit: $limit),
            fn (): array => $this->source->top($viewable, $query, $limit),
        );

        return $this->loader->load($rows);
    }

    private function cacheKey(?Viewable $viewable): CacheKey
    {
        return new CacheKey($viewable, $this->view->getConnection(), $this->config->cacheKey(), $this->config->sourceDriver());
    }

    /**
     * The counts the cache holds, keyed by viewable key.
     *
     * @param  array<int|string, string>  $cacheKeys
     * @return array<int|string, int>
     */
    private function cached(array $cacheKeys): array
    {
        if ($cacheKeys === []) {
            return [];
        }

        $keys = array_flip($cacheKeys);
        $counts = [];

        foreach ($this->cache->getMultiple(array_keys($keys)) as $cacheKey => $count) {
            if (is_int($count) && isset($keys[$cacheKey])) {
                $counts[$keys[$cacheKey]] = $count;
            }
        }

        return $counts;
    }

    /**
     * @param  array<int|string, string>  $cacheKeys
     * @param  array<int|string, int>  $counts
     */
    private function cacheMany(array $cacheKeys, array $counts, CarbonInterface $until): void
    {
        $values = [];

        foreach ($cacheKeys as $key => $cacheKey) {
            if (isset($counts[$key])) {
                $values[$cacheKey] = $counts[$key];
            }
        }

        // The PSR contract takes an interval, not a moment. One that lies in
        // the past makes the repository forget the keys, as put() does.
        $this->cache->setMultiple($values, Carbon::now()->diff($until));
    }

    /**
     * The entry `count()` uses for each viewable, keyed by viewable key.
     *
     * @return array<int|string, string>
     */
    private function cacheKeys(ViewableSet $viewables, ViewsQuery $query): array
    {
        return array_map(
            fn (Viewable $viewable): string => $this->cacheKey($viewable)->make($query),
            $viewables->all(),
        );
    }

    /**
     * @template TValue of int|array<string, int>|list<array{type: string, id: int|string, count: int}>
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
