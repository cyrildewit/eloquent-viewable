<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Querying;

use Carbon\Carbon;
use Carbon\CarbonInterface;
use CyrildeWit\EloquentViewable\Contracts\Viewable;
use CyrildeWit\EloquentViewable\Exceptions\InvalidPeriod;
use CyrildeWit\EloquentViewable\Exceptions\InvalidViewable;
use CyrildeWit\EloquentViewable\Querying\Cache\RememberingSource;
use CyrildeWit\EloquentViewable\Querying\Cache\VersionedCache;
use CyrildeWit\EloquentViewable\Querying\Comparison\ViewComparison;
use CyrildeWit\EloquentViewable\Querying\Contracts\IdentifiesSource;
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

/**
 * Reads counts from the source, through the cache when the call asks to
 * remember them, and shapes them: it checks the arguments, fills in empty
 * buckets and zeros, sorts and loads the ranked models.
 */
final readonly class Reader
{
    public function __construct(
        private ViewSource $source,
        private VersionedCache $cache,
        private Config $config,
        private ViewableLoader $loader,
    ) {}

    public function count(Viewable $viewable, ViewsQuery $query, ?CarbonInterface $rememberUntil = null): int
    {
        return $this->source($rememberUntil)->count($viewable, $query);
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
        $keys = $viewables->keys();

        // A set with a type always has keys; the second check tells PHPStan.
        if (! $type instanceof Viewable || $keys === []) {
            return [];
        }

        $counts = $this->source($rememberUntil)->countMany($type, $keys, $query);

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

        $counts = $this->source($rememberUntil)->countByInterval($viewable, $query, $granularity);

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
        $counts = $this->source($rememberUntil)->countByCollection($viewable, $query);

        return $this->sortByCountThenName($counts);
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

        return $this->loader->load($this->source($rememberUntil)->top($viewable, $query, $limit));
    }

    /**
     * This is the source itself when the call does not ask to remember the
     * result, and the source behind the cache until that moment when it does.
     */
    private function source(?CarbonInterface $rememberUntil): ViewSource
    {
        if (! $rememberUntil instanceof CarbonInterface) {
            return $this->source;
        }

        return new RememberingSource($this->source, $this->cache, $rememberUntil, $this->config->cacheKey(), $this->identity());
    }

    /**
     * The identity is the driver name, followed by what the source reports
     * about itself when it implements `IdentifiesSource`.
     */
    private function identity(): string
    {
        $driver = $this->config->sourceDriver();

        if (! $this->source instanceof IdentifiesSource) {
            return $driver;
        }

        return "{$driver}:{$this->source->cacheIdentity()}";
    }

    /**
     * @param  array<string, int>  $counts
     * @return array<string, int>
     */
    private function sortByCountThenName(array $counts): array
    {
        ksort($counts, SORT_STRING);
        arsort($counts);

        return $counts;
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
