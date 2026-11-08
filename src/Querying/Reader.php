<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Querying;

use Carbon\CarbonInterface;
use Carbon\CarbonInterval;
use CyrildeWit\EloquentViewable\Contracts\Viewable;
use CyrildeWit\EloquentViewable\Dimensions\DimensionDefinition;
use CyrildeWit\EloquentViewable\Exceptions\InvalidConfiguration;
use CyrildeWit\EloquentViewable\Exceptions\InvalidPeriod;
use CyrildeWit\EloquentViewable\Exceptions\InvalidViewable;
use CyrildeWit\EloquentViewable\Exceptions\InvalidViewer;
use CyrildeWit\EloquentViewable\Querying\Cache\Lifetime;
use CyrildeWit\EloquentViewable\Querying\Cache\RememberingSource;
use CyrildeWit\EloquentViewable\Querying\Cache\VersionedCache;
use CyrildeWit\EloquentViewable\Querying\Comparison\ViewComparison;
use CyrildeWit\EloquentViewable\Querying\Contracts\CountsBy;
use CyrildeWit\EloquentViewable\Querying\Contracts\CountsByDimension;
use CyrildeWit\EloquentViewable\Querying\Contracts\CountsByWindow;
use CyrildeWit\EloquentViewable\Querying\Contracts\CountsVisitFrequency;
use CyrildeWit\EloquentViewable\Querying\Contracts\IdentifiesSource;
use CyrildeWit\EloquentViewable\Querying\Contracts\RanksAlsoViewed;
use CyrildeWit\EloquentViewable\Querying\Contracts\RanksRecommendations;
use CyrildeWit\EloquentViewable\Querying\Contracts\RanksTrending;
use CyrildeWit\EloquentViewable\Querying\Contracts\ViewSource;
use CyrildeWit\EloquentViewable\Querying\Dimensions\DimensionCounts;
use CyrildeWit\EloquentViewable\Querying\Exceptions\InvalidBaseline;
use CyrildeWit\EloquentViewable\Querying\Exceptions\InvalidDecay;
use CyrildeWit\EloquentViewable\Querying\Exceptions\InvalidFrequency;
use CyrildeWit\EloquentViewable\Querying\Exceptions\InvalidInterval;
use CyrildeWit\EloquentViewable\Querying\Exceptions\InvalidLimit;
use CyrildeWit\EloquentViewable\Querying\Exceptions\UnsupportedBySource;
use CyrildeWit\EloquentViewable\Querying\Frequency\VisitFrequency;
use CyrildeWit\EloquentViewable\Querying\Growth\Baseline;
use CyrildeWit\EloquentViewable\Querying\Growth\GrowthRanking;
use CyrildeWit\EloquentViewable\Querying\Growth\Seasonality;
use CyrildeWit\EloquentViewable\Querying\Ranking\DecayCurve;
use CyrildeWit\EloquentViewable\Querying\Ranking\DecayFactory;
use CyrildeWit\EloquentViewable\Querying\Ranking\Ranking;
use CyrildeWit\EloquentViewable\Querying\Ranking\ViewableLoader;
use CyrildeWit\EloquentViewable\Querying\Recommendations\Recipient;
use CyrildeWit\EloquentViewable\Querying\Recommendations\RecommendationLoader;
use CyrildeWit\EloquentViewable\Querying\Recommendations\Recommendations;
use CyrildeWit\EloquentViewable\Querying\Recommendations\Recommender;
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
        private RecommendationLoader $recommendations,
        private Recommender $recommender,
        private GrowthRanking $growth = new GrowthRanking,
    ) {}

    public function count(Viewable $viewable, ViewsQuery $query, ?Lifetime $lifetime = null): int
    {
        return $this->source($lifetime)->count($viewable, $query);
    }

    /**
     * Two counts, one over the period and one over `Period::previous()`,
     * each remembered under its own key. With `$returning`, both count the
     * visitors who came back instead of the views.
     *
     * @throws InvalidPeriod
     * @throws UnsupportedBySource
     */
    public function compare(Viewable $viewable, ViewsQuery $query, ?Lifetime $lifetime = null, bool $returning = false): ViewComparison
    {
        $period = $query->period ?? throw InvalidPeriod::comparedWithoutPeriod();
        $previous = $period->previous();

        $count = $returning
            ? fn (ViewsQuery $query): int => $this->returning($viewable, $query, $lifetime)
            : fn (ViewsQuery $query): int => $this->count($viewable, $query, $lifetime);

        return ViewComparison::between(
            $count($query),
            $count($query->withPeriod($previous)),
            $period,
            $previous,
        );
    }

    /**
     * The number of visitors who viewed on two or more days.
     *
     * @throws UnsupportedBySource
     */
    public function returning(Viewable $viewable, ViewsQuery $query, ?Lifetime $lifetime = null): int
    {
        return VisitFrequency::fold($this->visitFrequency($viewable, $query, $lifetime), 2)->returning();
    }

    /**
     * @throws InvalidFrequency
     * @throws UnsupportedBySource
     */
    public function countByFrequency(Viewable $viewable, ViewsQuery $query, int $upTo, ?Lifetime $lifetime = null): VisitFrequency
    {
        if ($upTo < 2) {
            throw InvalidFrequency::capBelowTwo($upTo);
        }

        return VisitFrequency::fold($this->visitFrequency($viewable, $query, $lifetime), $upTo);
    }

    /** @return array<int|string, int> */
    public function countMany(ViewableSet $viewables, ViewsQuery $query, ?Lifetime $lifetime = null): array
    {
        $type = $viewables->type();
        $keys = $viewables->keys();

        if (! $type instanceof Viewable || $keys === []) {
            return [];
        }

        $counts = $this->source($lifetime)->countMany($type, $keys, $query);

        return array_replace(array_fill_keys(array_keys($viewables->all()), 0), $counts);
    }

    /** @throws InvalidInterval */
    public function countByInterval(Viewable $viewable, ViewsQuery $query, Granularity $granularity, ?Lifetime $lifetime = null): ViewSeries
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
            $period->endOrNow()->avoidMutation()->setTimezone($timezone),
        );

        $counts = $this->source($lifetime)->countByInterval($viewable, $query, $granularity);

        return ViewSeries::fill($period, $granularity, $counts, $timezone);
    }

    /**
     * Sorted like `DimensionCounts::sorted()`.
     *
     * @return array<string, int>
     */
    public function countByCollection(Viewable $viewable, ViewsQuery $query, ?Lifetime $lifetime = null): array
    {
        return DimensionCounts::sorted($this->source($lifetime)->countByCollection($viewable, $query));
    }

    /**
     * Most viewed first, then by value, like `countByCollection()`.
     *
     * @return array<string, int>
     *
     * @throws UnsupportedBySource
     */
    public function countByDimension(Viewable $viewable, ViewsQuery $query, string $dimension, ?Lifetime $lifetime = null): array
    {
        return DimensionCounts::sorted($this->requires(CountsByDimension::class, $lifetime)->countByDimension($viewable, $query, $dimension));
    }

    /**
     * @throws InvalidLimit
     * @throws UnsupportedBySource
     */
    public function countBy(Viewable $viewable, ViewsQuery $query, DimensionDefinition $dimension, ?int $limit = null, ?Lifetime $lifetime = null): DimensionCounts
    {
        $this->guardLimit($limit ?? 1, 'countBy()');

        return $this->requires(CountsBy::class, $lifetime)->countBy($viewable, $query, $dimension, $limit);
    }

    /**
     * @throws InvalidLimit
     * @throws InvalidViewable
     */
    public function top(?Viewable $viewable, ViewsQuery $query, int $limit, ?Lifetime $lifetime = null): Ranking
    {
        $this->guardRanking($viewable, $limit, 'top()');

        return $this->loader->load($this->source($lifetime)->top($viewable, $query, $limit));
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
    public function trending(?Viewable $viewable, ViewsQuery $query, int $limit, ?CarbonInterval $halfLife = null, ?DecayCurve $curve = null, ?Lifetime $lifetime = null): Ranking
    {
        $this->guardRanking($viewable, $limit, 'trending()');

        $decay = $this->decays->make($query, $halfLife, $curve);

        return $this->loader->load($this->requires(RanksTrending::class, $lifetime)->trending($viewable, $query, $decay, $limit));
    }

    /**
     * Ranked by the count in the period divided by the count in the previous
     * period, highest first. Only a viewable that grew, and reached the
     * minimum in one of the two periods, is ranked.
     *
     * @throws InvalidBaseline
     * @throws InvalidLimit
     * @throws InvalidPeriod
     * @throws InvalidViewable
     * @throws UnsupportedBySource
     */
    public function rising(?Viewable $viewable, ViewsQuery $query, int $limit, int $minimum, ?Lifetime $lifetime = null): Ranking
    {
        $source = $this->growthSource($viewable, $limit, $minimum, 'rising()', $lifetime);

        return $this->loader->load($this->growth->rising($source, $viewable, $query, $minimum, $limit));
    }

    /**
     * It ranks by how many deviations the count in the period lies from the
     * same period on past days or weeks. A positive threshold ranks spikes,
     * highest first, a negative one drops, lowest first.
     *
     * @throws InvalidBaseline
     * @throws InvalidLimit
     * @throws InvalidPeriod
     * @throws InvalidViewable
     * @throws UnsupportedBySource
     */
    public function anomalies(?Viewable $viewable, ViewsQuery $query, float $threshold, int $minimum, int $limit, Seasonality $seasonality, int $samples, ?Lifetime $lifetime = null): Ranking
    {
        $source = $this->growthSource($viewable, $limit, $minimum, 'anomalies()', $lifetime);

        return $this->loader->load($this->growth->anomalies($source, $viewable, $query, $seasonality, $samples, $threshold, $minimum, $limit));
    }

    /**
     * It returns the count in the period next to the counts of the same
     * period on past days or weeks, each remembered under its own key.
     *
     * @throws InvalidBaseline
     * @throws InvalidPeriod
     */
    public function againstBaseline(Viewable $viewable, ViewsQuery $query, Seasonality $seasonality, int $samples, ?Lifetime $lifetime = null): Baseline
    {
        $period = $query->period ?? throw InvalidBaseline::withoutStart();

        $references = array_map(
            fn (Period $reference): int => $this->count($viewable, $query->withPeriod($reference), $lifetime),
            $seasonality->references($period, $samples),
        );

        return new Baseline($this->count($viewable, $query, $lifetime), $references);
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
    public function alsoViewed(Viewable $viewable, ?Viewable $among, ViewsQuery $query, int $limit, ?Lifetime $lifetime = null): Ranking
    {
        $this->guardLimit($limit, 'alsoViewed()');

        if (ViewableKey::of($viewable) === null) {
            throw InvalidViewable::cannotPairType($viewable);
        }

        if ($query->viewer instanceof Model) {
            throw InvalidViewer::cannotNarrowAlsoViewed();
        }

        return $this->loader->load($this->requires(RanksAlsoViewed::class, $lifetime)->alsoViewed(
            $viewable,
            $among,
            $query,
            $limit,
            $this->config->alsoViewedMinimumVisitors(),
            $this->config->alsoViewedMaxVisitors(),
        ));
    }

    /**
     * What the visitors of the viewables the recipient viewed recently also
     * viewed, weighed by how recently the recipient viewed each and how
     * closely the two follow each other. The query is matched on every side;
     * a viewer it names is the recipient, not a narrowing.
     *
     * @throws InvalidConfiguration
     * @throws InvalidLimit
     * @throws InvalidViewable
     * @throws UnsupportedBySource
     */
    public function recommended(Recipient $recipient, ?Viewable $among, ViewsQuery $query, int $limit, bool $includeSeen = false, ?Lifetime $lifetime = null): Recommendations
    {
        $this->guardLimit($limit, 'recommended()');

        return $this->recommendations->load($this->scoreRecommendations($recipient, $among, $query, $includeSeen, $limit, $lifetime));
    }

    /**
     * The score of every viewable of the type recommended to the recipient,
     * highest first, keyed by its key.
     *
     * @return array<int|string, float>
     *
     * @throws InvalidConfiguration
     * @throws InvalidViewable
     * @throws UnsupportedBySource
     */
    public function recommendationScores(Recipient $recipient, Viewable $among, ViewsQuery $query, bool $includeSeen = false): array
    {
        $scores = [];

        foreach ($this->scoreRecommendations($recipient, $among, $query, $includeSeen) as $row) {
            $scores[$row['id']] = $row['score'];
        }

        return $scores;
    }

    /**
     * @return list<array{type: string, id: int|string, score: float, because: list<array{type: string, id: int|string}>}>
     *
     * @throws InvalidConfiguration
     * @throws InvalidViewable
     * @throws UnsupportedBySource
     */
    private function scoreRecommendations(Recipient $recipient, ?Viewable $among, ViewsQuery $query, bool $includeSeen, ?int $limit = null, ?Lifetime $lifetime = null): array
    {
        if ($among instanceof Viewable && ViewableKey::of($among) !== null) {
            throw InvalidViewable::cannotRecommendAmongOne($among);
        }

        return $this->recommender->score($this->requires(RanksRecommendations::class, $lifetime), $recipient, $among, $query, $includeSeen, $limit);
    }

    /**
     * @return array<int, int>
     *
     * @throws UnsupportedBySource
     */
    private function visitFrequency(Viewable $viewable, ViewsQuery $query, ?Lifetime $lifetime): array
    {
        return $this->requires(CountsVisitFrequency::class, $lifetime)->visitFrequency($viewable, $query);
    }

    /**
     * @throws InvalidBaseline
     * @throws InvalidLimit
     * @throws InvalidViewable
     * @throws UnsupportedBySource
     */
    private function growthSource(?Viewable $viewable, int $limit, int $minimum, string $method, ?Lifetime $lifetime): CountsByWindow
    {
        $this->guardRanking($viewable, $limit, $method);

        if ($minimum < 1) {
            throw InvalidBaseline::minimumBelowOne($minimum, $method);
        }

        return $this->requires(CountsByWindow::class, $lifetime);
    }

    /** @throws InvalidLimit */
    private function guardLimit(int $limit, string $method): void
    {
        if ($limit < 1) {
            throw InvalidLimit::belowOne($limit, $method);
        }
    }

    /**
     * A ranking is read across a type, or across every type, never for one
     * model.
     *
     * @throws InvalidLimit
     * @throws InvalidViewable
     */
    private function guardRanking(?Viewable $viewable, int $limit, string $method): void
    {
        $this->guardLimit($limit, $method);

        if ($viewable instanceof Viewable && ViewableKey::of($viewable) !== null) {
            throw InvalidViewable::cannotRankOne($viewable);
        }
    }

    /**
     * The source for a read that needs one of the optional contracts, behind
     * the cache when the call asks to remember the result.
     *
     * @template TContract of object
     *
     * @param  class-string<TContract>  $contract
     * @return TContract&ViewSource
     *
     * @throws UnsupportedBySource
     */
    private function requires(string $contract, ?Lifetime $lifetime): ViewSource
    {
        $source = $this->source($lifetime);

        if (! $source instanceof $contract) {
            throw UnsupportedBySource::missing($contract, $source);
        }

        return $source;
    }

    /**
     * This is the source itself when the call does not ask to remember the
     * result, and the source behind the cache for that lifetime when it does.
     */
    private function source(?Lifetime $lifetime): ViewSource
    {
        if (! $lifetime instanceof Lifetime) {
            return $this->source;
        }

        return new RememberingSource($this->source, $this->cache, $lifetime, $this->config->cacheKey(), $this->identity());
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
