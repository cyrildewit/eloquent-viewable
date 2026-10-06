<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Querying;

use Carbon\Carbon;
use Carbon\CarbonInterface;
use Carbon\CarbonInterval;
use CyrildeWit\EloquentViewable\Contracts\Viewable;
use CyrildeWit\EloquentViewable\Exceptions\InvalidConfiguration;
use CyrildeWit\EloquentViewable\Exceptions\InvalidPeriod;
use CyrildeWit\EloquentViewable\Exceptions\InvalidViewable;
use CyrildeWit\EloquentViewable\Exceptions\InvalidViewer;
use CyrildeWit\EloquentViewable\Querying\Cache\RememberingSource;
use CyrildeWit\EloquentViewable\Querying\Cache\VersionedCache;
use CyrildeWit\EloquentViewable\Querying\Comparison\ViewComparison;
use CyrildeWit\EloquentViewable\Querying\Contracts\CountsByDimension;
use CyrildeWit\EloquentViewable\Querying\Contracts\CountsVisitFrequency;
use CyrildeWit\EloquentViewable\Querying\Contracts\IdentifiesSource;
use CyrildeWit\EloquentViewable\Querying\Contracts\RanksAlsoViewed;
use CyrildeWit\EloquentViewable\Querying\Contracts\RanksTrending;
use CyrildeWit\EloquentViewable\Querying\Contracts\ViewSource;
use CyrildeWit\EloquentViewable\Querying\Exceptions\InvalidDecay;
use CyrildeWit\EloquentViewable\Querying\Exceptions\InvalidFrequency;
use CyrildeWit\EloquentViewable\Querying\Exceptions\InvalidInterval;
use CyrildeWit\EloquentViewable\Querying\Exceptions\InvalidLimit;
use CyrildeWit\EloquentViewable\Querying\Exceptions\UnsupportedBySource;
use CyrildeWit\EloquentViewable\Querying\Frequency\VisitFrequency;
use CyrildeWit\EloquentViewable\Querying\Ranking\DecayCurve;
use CyrildeWit\EloquentViewable\Querying\Ranking\DecayFactory;
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
use Illuminate\Database\Eloquent\Model;

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
        private DecayFactory $decays,
    ) {}

    public function count(Viewable $viewable, ViewsQuery $query, ?CarbonInterface $rememberUntil = null): int
    {
        return $this->source($rememberUntil)->count($viewable, $query);
    }

    /**
     * Two counts, one over the period and one over `Period::previous()`,
     * each remembered under its own key. With `$returning`, both count the
     * visitors who came back instead of the views.
     *
     * @throws InvalidPeriod
     * @throws UnsupportedBySource
     */
    public function compare(Viewable $viewable, ViewsQuery $query, ?CarbonInterface $rememberUntil = null, bool $returning = false): ViewComparison
    {
        $period = $query->period ?? throw InvalidPeriod::comparedWithoutPeriod();
        $previous = $period->previous();

        $count = $returning
            ? fn (ViewsQuery $query): int => $this->returning($viewable, $query, $rememberUntil)
            : fn (ViewsQuery $query): int => $this->count($viewable, $query, $rememberUntil);

        return ViewComparison::between(
            $count($query),
            $count($query->withPeriod($previous)),
            $period,
            $previous,
        );
    }

    /**
     * The visitors who viewed on two days or more.
     *
     * @throws UnsupportedBySource
     */
    public function returning(Viewable $viewable, ViewsQuery $query, ?CarbonInterface $rememberUntil = null): int
    {
        return VisitFrequency::fold($this->visitFrequency($viewable, $query, $rememberUntil), 2)->returning();
    }

    /**
     * @throws InvalidFrequency
     * @throws UnsupportedBySource
     */
    public function countByFrequency(Viewable $viewable, ViewsQuery $query, int $upTo, ?CarbonInterface $rememberUntil = null): VisitFrequency
    {
        if ($upTo < 2) {
            throw InvalidFrequency::capBelowTwo($upTo);
        }

        return VisitFrequency::fold($this->visitFrequency($viewable, $query, $rememberUntil), $upTo);
    }

    /** @return array<int|string, int> */
    public function countMany(ViewableSet $viewables, ViewsQuery $query, ?CarbonInterface $rememberUntil = null): array
    {
        $type = $viewables->type();
        $keys = $viewables->keys();

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
     * Most viewed first, then by value, like `countByCollection()`.
     *
     * @return array<string, int>
     *
     * @throws UnsupportedBySource
     */
    public function countByDimension(Viewable $viewable, ViewsQuery $query, string $dimension, ?CarbonInterface $rememberUntil = null): array
    {
        $source = $this->source($rememberUntil);

        if (! $source instanceof CountsByDimension) {
            throw UnsupportedBySource::dimension($source);
        }

        $counts = $source->countByDimension($viewable, $query, $dimension);

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
     * Ranked by views weighed by their age, so recent views count more. The
     * half-life is a shorthand for exponential decay, so only one of the two
     * may be given; without either, the configured curve is used.
     *
     * @throws InvalidConfiguration
     * @throws InvalidDecay
     * @throws InvalidLimit
     * @throws InvalidViewable
     * @throws UnsupportedBySource
     */
    public function trending(?Viewable $viewable, ViewsQuery $query, int $limit, ?CarbonInterval $halfLife = null, ?DecayCurve $curve = null, ?CarbonInterface $rememberUntil = null): Ranking
    {
        if ($limit < 1) {
            throw InvalidLimit::belowOne($limit, 'trending()');
        }

        if ($viewable instanceof Viewable && ViewableKey::of($viewable) !== null) {
            throw InvalidViewable::cannotRankOne($viewable);
        }

        $decay = $this->decays->make($query, $halfLife, $curve);
        $source = $this->source($rememberUntil);

        if (! $source instanceof RanksTrending) {
            throw UnsupportedBySource::trending($source);
        }

        return $this->loader->load($source->trending($viewable, $query, $decay, $limit));
    }

    /**
     * What the visitors of the viewable also viewed, ranked by how many of
     * them did. The count is always of distinct visitors, so `unique()` makes
     * no difference.
     *
     * @throws InvalidConfiguration
     * @throws InvalidLimit
     * @throws InvalidViewable
     * @throws InvalidViewer
     * @throws UnsupportedBySource
     */
    public function alsoViewed(Viewable $viewable, ?Viewable $among, ViewsQuery $query, int $limit, ?CarbonInterface $rememberUntil = null): Ranking
    {
        if ($limit < 1) {
            throw InvalidLimit::belowOne($limit, 'alsoViewed()');
        }

        if (ViewableKey::of($viewable) === null) {
            throw InvalidViewable::cannotPairType($viewable);
        }

        if ($query->viewer instanceof Model) {
            throw InvalidViewer::cannotNarrowAlsoViewed();
        }

        $source = $this->source($rememberUntil);

        if (! $source instanceof RanksAlsoViewed) {
            throw UnsupportedBySource::alsoViewed($source);
        }

        return $this->loader->load($source->alsoViewed(
            $viewable,
            $among,
            $query,
            $limit,
            $this->config->alsoViewedMinimumVisitors(),
            $this->config->alsoViewedMaxVisitors(),
        ));
    }

    /**
     * @return array<int, int>
     *
     * @throws UnsupportedBySource
     */
    private function visitFrequency(Viewable $viewable, ViewsQuery $query, ?CarbonInterface $rememberUntil): array
    {
        $source = $this->source($rememberUntil);

        if (! $source instanceof CountsVisitFrequency) {
            throw UnsupportedBySource::visitFrequency($source);
        }

        return $source->visitFrequency($viewable, $query);
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
