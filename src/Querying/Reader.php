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
use CyrildeWit\EloquentViewable\Querying\Cache\CacheVersions;
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
    private CacheVersions $versions;

    public function __construct(
        private ViewSource $source,
        private CacheRepository $cache,
        private Config $config,
        private View $view,
        private ViewableLoader $loader,
    ) {
        // Built here so the versions always live in the entries' store.
        $this->versions = new CacheVersions($cache, $config);
    }

    public function count(Viewable $viewable, ViewsQuery $query, ?CarbonInterface $rememberUntil = null): int
    {
        return $this->remember(
            $rememberUntil,
            $viewable,
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

    /** @return array<int|string, int> */
    public function countMany(ViewableSet $viewables, ViewsQuery $query, ?CarbonInterface $rememberUntil = null): array
    {
        $type = $viewables->type();

        if (! $type instanceof Viewable) {
            return [];
        }

        $entries = $rememberUntil instanceof CarbonInterface ? $this->entries($viewables, $query) : [];
        $counts = $this->cached($entries);
        $missing = array_values(array_filter($viewables->keys(), fn (int|string $key): bool => ! isset($counts[$key])));

        if ($missing !== []) {
            $fetched = $this->source->countMany($type, $missing, $query);
            $fresh = [];

            foreach ($missing as $key) {
                $fresh[$key] = $fetched[$key] ?? 0;
            }

            if ($rememberUntil instanceof CarbonInterface) {
                $this->cacheMany($entries, $fresh, $rememberUntil);
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
            $viewable,
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
            $viewable,
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
            $viewable,
            fn (): string => $this->cacheKey($viewable)->make($query, limit: $limit),
            fn (): array => $this->source->top($viewable, $query, $limit),
        );

        return $this->loader->load($rows);
    }

    private function cacheKey(?Viewable $viewable): CacheKey
    {
        return new CacheKey($viewable, $this->view->getConnection(), $this->config->cacheKey(), $this->config->sourceDriver());
    }

    /** @return array<int|string, array{key: string, version: string, cached: mixed}> */
    private function entries(ViewableSet $viewables, ViewsQuery $query): array
    {
        $cacheKeys = [];
        $versionKeys = [];

        foreach ($viewables->all() as $key => $viewable) {
            $cacheKeys[$key] = $this->cacheKey($viewable)->make($query);
            $versionKeys[$key] = $this->versions->keys($viewable);
        }

        $shared = array_values(array_unique(array_merge(...array_values($versionKeys))));
        $read = $this->read([...array_values($cacheKeys), ...$shared]);
        $versions = $this->versions->resolve($shared, $read);
        $entries = [];

        foreach ($cacheKeys as $key => $cacheKey) {
            $entries[$key] = [
                'key' => $cacheKey,
                'version' => $this->versions->stamp($versions, $versionKeys[$key] ?? []),
                'cached' => $read[$cacheKey] ?? null,
            ];
        }

        return $entries;
    }

    /**
     * @param  array<int|string, array{key: string, version: string, cached: mixed}>  $entries
     * @return array<int|string, int>
     */
    private function cached(array $entries): array
    {
        $counts = [];

        foreach ($entries as $key => $entry) {
            if ($this->isCurrent($entry['cached'], $entry['version']) && is_int($entry['cached']['value'])) {
                $counts[$key] = $entry['cached']['value'];
            }
        }

        return $counts;
    }

    /**
     * @param  array<int|string, array{key: string, version: string, cached: mixed}>  $entries
     * @param  array<int|string, int>  $counts
     */
    private function cacheMany(array $entries, array $counts, CarbonInterface $until): void
    {
        $values = [];

        foreach ($entries as $key => $entry) {
            if (isset($counts[$key])) {
                $values[$entry['key']] = ['version' => $entry['version'], 'value' => $counts[$key]];
            }
        }

        // The PSR contract takes an interval, not a moment. One that lies in
        // the past makes the repository forget the keys, as put() does.
        $this->cache->setMultiple($values, Carbon::now()->diff($until));
    }

    /**
     * @template TValue of int|array<string, int>|list<array{type: string, id: int|string, count: int}>
     *
     * @param  Closure(): string  $key
     * @param  Closure(): TValue  $resolve
     * @return TValue
     */
    private function remember(?CarbonInterface $until, ?Viewable $viewable, Closure $key, Closure $resolve): int|array
    {
        if (! $until instanceof CarbonInterface) {
            return $resolve();
        }

        $cacheKey = $key();
        $versionKeys = $this->versions->keys($viewable);
        $read = $this->read([$cacheKey, ...$versionKeys]);
        $version = $this->versions->stamp($this->versions->resolve($versionKeys, $read), $versionKeys);
        $cached = $read[$cacheKey] ?? null;

        if ($this->isCurrent($cached, $version)) {
            /** @var TValue $value */
            $value = $cached['value'];

            return $value;
        }

        $value = $resolve();

        $this->cache->put($cacheKey, ['version' => $version, 'value' => $value], $until);

        return $value;
    }

    /** @phpstan-assert-if-true array{version: string, value: mixed} $cached */
    private function isCurrent(mixed $cached, string $version): bool
    {
        return is_array($cached) && ($cached['version'] ?? null) === $version && array_key_exists('value', $cached);
    }

    /**
     * @param  list<string>  $keys
     * @return array<string, mixed>
     */
    private function read(array $keys): array
    {
        $read = [];

        foreach ($this->cache->getMultiple($keys) as $key => $value) {
            $read[$key] = $value;
        }

        return $read;
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
