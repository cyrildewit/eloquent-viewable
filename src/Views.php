<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable;

use Carbon\Carbon;
use Carbon\CarbonInterface;
use Carbon\CarbonInterval;
use CyrildeWit\EloquentViewable\Contracts\Viewable;
use CyrildeWit\EloquentViewable\Exceptions\InvalidConfiguration;
use CyrildeWit\EloquentViewable\Exceptions\InvalidPeriod;
use CyrildeWit\EloquentViewable\Exceptions\InvalidTimezone;
use CyrildeWit\EloquentViewable\Exceptions\InvalidViewable;
use CyrildeWit\EloquentViewable\Exceptions\InvalidViewer;
use CyrildeWit\EloquentViewable\Presence\LiveViews;
use CyrildeWit\EloquentViewable\Querying\Cache\CacheVersions;
use CyrildeWit\EloquentViewable\Querying\Comparison\ViewComparison;
use CyrildeWit\EloquentViewable\Querying\Exceptions\InvalidBaseline;
use CyrildeWit\EloquentViewable\Querying\Exceptions\InvalidDecay;
use CyrildeWit\EloquentViewable\Querying\Exceptions\InvalidFrequency;
use CyrildeWit\EloquentViewable\Querying\Exceptions\InvalidInterval;
use CyrildeWit\EloquentViewable\Querying\Exceptions\InvalidLimit;
use CyrildeWit\EloquentViewable\Querying\Exceptions\InvalidReturning;
use CyrildeWit\EloquentViewable\Querying\Exceptions\UnsupportedBySource;
use CyrildeWit\EloquentViewable\Querying\Frequency\VisitFrequency;
use CyrildeWit\EloquentViewable\Querying\Growth\Baseline;
use CyrildeWit\EloquentViewable\Querying\Growth\Seasonality;
use CyrildeWit\EloquentViewable\Querying\Ranking\DecayCurve;
use CyrildeWit\EloquentViewable\Querying\Ranking\Ranking;
use CyrildeWit\EloquentViewable\Querying\Reader;
use CyrildeWit\EloquentViewable\Querying\Recommendations\Recipient;
use CyrildeWit\EloquentViewable\Querying\Recommendations\Recommendations;
use CyrildeWit\EloquentViewable\Querying\Rollups\Exceptions\UnknownRollup;
use CyrildeWit\EloquentViewable\Querying\Rollups\Rollup;
use CyrildeWit\EloquentViewable\Querying\Rollups\RollupPolicy;
use CyrildeWit\EloquentViewable\Querying\Series\ViewSeries;
use CyrildeWit\EloquentViewable\Recording\Actions\DestroyViews;
use CyrildeWit\EloquentViewable\Recording\Data\RecordResult;
use CyrildeWit\EloquentViewable\Recording\Data\ViewAttempt;
use CyrildeWit\EloquentViewable\Recording\Exceptions\RecordingFailed;
use CyrildeWit\EloquentViewable\Recording\Recorder;
use CyrildeWit\EloquentViewable\Support\Granularity;
use CyrildeWit\EloquentViewable\Support\Period;
use CyrildeWit\EloquentViewable\Support\Timezone;
use CyrildeWit\EloquentViewable\Support\ViewableSet;
use CyrildeWit\EloquentViewable\Support\ViewsQuery;
use CyrildeWit\EloquentViewable\Visitors\Contracts\Visitor as VisitorContract;
use DateTimeInterface;
use DateTimeZone;
use Illuminate\Container\Container;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Traits\Macroable;

class Views
{
    use Macroable;

    protected ?Viewable $viewable = null;

    protected ?ViewableSet $viewables = null;

    protected ?Period $period = null;

    protected bool $unique = false;

    protected bool $returning = false;

    protected ?CarbonInterface $cooldown = null;

    protected ?string $collection = null;

    protected ?bool $queue = null;

    protected ?CarbonInterface $cacheLifetime = null;

    protected ?Timezone $timezone = null;

    protected ?Model $viewer = null;

    /** @var ?array<string, mixed> */
    protected ?array $context = null;

    protected ?Rollup $rollup = null;

    public function __construct(
        protected VisitorContract $visitor,
        protected Recorder $recorder,
        protected Reader $reader,
        protected DestroyViews $destroyer,
        protected CacheVersions $cacheVersions,
    ) {}

    public function forViewable(Viewable $viewable): self
    {
        $this->viewable = $viewable;
        $this->viewables = null;

        return $this;
    }

    /**
     * @param  iterable<Viewable>  $viewables
     *
     * @throws InvalidViewable
     */
    public function forViewables(iterable $viewables): self
    {
        $this->viewables = ViewableSet::of($viewables);
        $this->viewable = null;

        return $this;
    }

    /** @throws UnsupportedBySource */
    public function count(): int
    {
        if ($this->returning) {
            return $this->reader->returning($this->viewable(), $this->query(), $this->cacheLifetime);
        }

        return $this->reader->count($this->viewable(), $this->query(), $this->cacheLifetime);
    }

    /**
     * @throws InvalidPeriod
     * @throws UnsupportedBySource
     */
    public function compare(): ViewComparison
    {
        return $this->reader->compare($this->viewable(), $this->query(), $this->cacheLifetime, $this->returning);
    }

    /**
     * How many visitors viewed on one day, on two, and so on, up to `$upTo`
     * days and more. A day is on the clock of `timezone()`.
     *
     * @throws InvalidFrequency
     * @throws UnsupportedBySource
     */
    public function countByFrequency(int $upTo = 3): VisitFrequency
    {
        return $this->reader->countByFrequency($this->viewable(), $this->query(), $upTo, $this->cacheLifetime);
    }

    /**
     * @return Collection<int|string, int>
     *
     * @throws InvalidReturning
     * @throws InvalidViewable
     */
    public function counts(): Collection
    {
        $this->guardReturning('counts()');

        $viewables = $this->viewables ?? throw InvalidViewable::missingSet();

        return new Collection($this->reader->countMany($viewables, $this->query(), $this->cacheLifetime));
    }

    /**
     * @throws InvalidInterval
     * @throws InvalidReturning
     */
    public function countByInterval(Granularity $granularity): ViewSeries
    {
        $this->guardReturning('countByInterval()');

        return $this->reader->countByInterval($this->viewable(), $this->query(), $granularity, $this->cacheLifetime);
    }

    /**
     * @return array<string, int>
     *
     * @throws InvalidReturning
     */
    public function countByCollection(): array
    {
        $this->guardReturning('countByCollection()');

        return $this->reader->countByCollection($this->viewable(), $this->query(), $this->cacheLifetime);
    }

    /**
     * @return array<string, int>
     *
     * @throws InvalidReturning
     * @throws UnknownRollup
     */
    public function countByDimension(): array
    {
        $this->guardReturning('countByDimension()');

        $dimension = $this->rollup?->dimension();

        if ($dimension === null) {
            throw UnknownRollup::withoutDimension($this->rollup?->name);
        }

        return $this->reader->countByDimension($this->viewable(), $this->query(), $dimension, $this->cacheLifetime);
    }

    /**
     * @throws InvalidLimit
     * @throws InvalidReturning
     * @throws InvalidViewable
     */
    public function top(int $limit = 10): Ranking
    {
        $this->guardReturning('top()');

        return $this->reader->top($this->viewable, $this->query(), $limit, $this->cacheLifetime);
    }

    /**
     * Ranked by views weighed by their age, so something taking off now ranks
     * above something that was busy last week. Pass a half-life or a curve to
     * override the configured one for this call.
     *
     * @throws InvalidConfiguration
     * @throws InvalidDecay
     * @throws InvalidLimit
     * @throws InvalidReturning
     * @throws InvalidViewable
     * @throws UnsupportedBySource
     */
    public function trending(int $limit = 10, ?CarbonInterval $halfLife = null, ?DecayCurve $curve = null): Ranking
    {
        $this->guardReturning('trending()');

        return $this->reader->trending($this->viewable, $this->query(), $limit, $halfLife, $curve, $this->cacheLifetime);
    }

    /**
     * Ranked by how much the count grew against the period before, so
     * something taking off ranks above something that is busy every day.
     * Only what grew and got at least the minimum in either period counts,
     * so going from 1 to 4 views never wins.
     *
     * @throws InvalidBaseline
     * @throws InvalidLimit
     * @throws InvalidPeriod
     * @throws InvalidReturning
     * @throws InvalidViewable
     * @throws UnsupportedBySource
     */
    public function rising(int $limit = 10, int $minimum = 10): Ranking
    {
        $this->guardReturning('rising()');

        return $this->reader->rising($this->viewable, $this->query(), $limit, $minimum, $this->cacheLifetime);
    }

    /**
     * Ranked by how far the count lies from the same period on past days or
     * weeks, in deviations. A positive threshold finds what is spiking, a
     * negative one what dropped.
     *
     * @throws InvalidBaseline
     * @throws InvalidLimit
     * @throws InvalidPeriod
     * @throws InvalidReturning
     * @throws InvalidViewable
     * @throws UnsupportedBySource
     */
    public function anomalies(float $threshold = 3.0, int $minimum = 10, int $limit = 10, Seasonality $seasonality = Seasonality::Week, int $samples = 4): Ranking
    {
        $this->guardReturning('anomalies()');

        return $this->reader->anomalies($this->viewable, $this->query(), $threshold, $minimum, $limit, $seasonality, $samples, $this->cacheLifetime);
    }

    /**
     * The count in the period next to the same period on past days or weeks,
     * with its mean, deviation, z-score and ratio.
     *
     * @throws InvalidBaseline
     * @throws InvalidPeriod
     * @throws InvalidReturning
     * @throws InvalidViewable
     */
    public function againstBaseline(Seasonality $seasonality = Seasonality::Week, int $samples = 4): Baseline
    {
        $this->guardReturning('againstBaseline()');

        return $this->reader->againstBaseline($this->viewable(), $this->query(), $seasonality, $samples, $this->cacheLifetime);
    }

    /**
     * What the visitors of the viewable also viewed, ranked by how many of
     * them did. Pass a model class to rank only models of that class.
     *
     * @param  class-string<Model&Viewable>|null  $among
     *
     * @throws InvalidConfiguration
     * @throws InvalidLimit
     * @throws InvalidReturning
     * @throws InvalidViewable
     * @throws InvalidViewer
     * @throws UnsupportedBySource
     */
    public function alsoViewed(int $limit = 10, ?string $among = null): Ranking
    {
        $this->guardReturning('alsoViewed()');

        return $this->reader->alsoViewed($this->viewable(), $this->among($among), $this->query(), $limit, $this->cacheLifetime);
    }

    /**
     * Who is looking right now. A model reads that model, a model class its
     * type, and no viewable, as on the facade, the whole site.
     */
    public function live(): LiveViews
    {
        return Container::getInstance()->make(LiveViews::class, [
            'viewable' => $this->viewable,
            'viewables' => $this->viewables,
            'collection' => $this->collection,
        ]);
    }

    /**
     * @throws InvalidConfiguration
     * @throws InvalidViewable
     */
    public function activeVisitors(): int
    {
        return $this->live()->count();
    }

    /**
     * Keeps the visitor active on the viewable without recording a view.
     * Returns false when a guard refused the visitor or presence is off.
     *
     * @throws RecordingFailed
     */
    public function heartbeat(): bool
    {
        return $this->recorder->heartbeat($this->newAttempt());
    }

    /**
     * Stops counting the visitor on the viewable at once.
     *
     * @throws RecordingFailed
     */
    public function leave(): void
    {
        $this->recorder->leave($this->newAttempt());
    }

    /**
     * What the visitors of the models the viewer viewed recently also viewed,
     * for the viewer `viewedBy()` names or, without one, for the current
     * visitor. A model type given to `views()` ranks only models of that
     * type. What the viewer viewed before is left out unless it is included.
     *
     * @throws InvalidConfiguration
     * @throws InvalidDecay
     * @throws InvalidLimit
     * @throws InvalidViewable
     * @throws InvalidViewer
     * @throws UnsupportedBySource
     */
    public function recommended(int $limit = 10, bool $includeSeen = false): Recommendations
    {
        $recipient = $this->viewer instanceof Model
            ? Recipient::viewer($this->viewer)
            : Recipient::visitor($this->visitor->id());

        return $this->reader->recommended($recipient, $this->viewable, $this->query(), $limit, $includeSeen, $this->cacheLifetime);
    }

    /** @throws RecordingFailed */
    public function record(): bool
    {
        return $this->attempt()->recorded;
    }

    /** @throws RecordingFailed */
    public function attempt(): RecordResult
    {
        return $this->recorder->record($this->newAttempt());
    }

    public function destroy(): void
    {
        $this->destroyer->handle($this->viewable());
    }

    /**
     * Also forgets the remembered totals and rankings that include the
     * viewable. A viewable without a key forgets its whole type.
     */
    public function forgetCache(): void
    {
        $this->cacheVersions->forgetCache($this->viewable());
    }

    public function flushCache(): void
    {
        $this->cacheVersions->flushCache();
    }

    public function cooldown(DateTimeInterface|int|null $cooldown): self
    {
        $this->cooldown = $cooldown === null ? null : $this->resolveLifetime($cooldown);

        return $this;
    }

    public function period(?Period $period): self
    {
        $this->period = $period;

        return $this;
    }

    /** @throws InvalidTimezone */
    public function timezone(DateTimeZone|string|null $timezone): self
    {
        $this->timezone = $timezone === null ? null : Timezone::from($timezone);

        return $this;
    }

    public function collection(?string $name): self
    {
        $this->collection = $name;

        return $this;
    }

    public function viewedBy(?Model $viewer): self
    {
        $this->viewer = $viewer;

        return $this;
    }

    /** @param  ?array<string, mixed>  $context */
    public function context(?array $context): self
    {
        $this->context = $context;

        return $this;
    }

    public function queue(bool $state = true): self
    {
        $this->queue = $state;

        return $this;
    }

    /** @throws UnknownRollup */
    public function rollup(?string $name): self
    {
        if ($name === null) {
            $this->rollup = null;

            return $this;
        }

        $rollup = Container::getInstance()->make(RollupPolicy::class)->find($name)?->rollup();

        if (! $rollup instanceof Rollup) {
            throw UnknownRollup::named($name);
        }

        $this->rollup = $rollup;

        return $this;
    }

    public function unique(bool $state = true): self
    {
        $this->unique = $state;

        return $this;
    }

    /**
     * Counts the visitors who viewed on two days or more instead of the
     * views, in `count()` and `compare()`. A day is on the clock of
     * `timezone()`.
     */
    public function returning(bool $state = true): self
    {
        $this->returning = $state;

        return $this;
    }

    public function remember(DateTimeInterface|int|null $lifetime = null): self
    {
        $this->cacheLifetime = $lifetime === null ? null : $this->resolveLifetime($lifetime);

        return $this;
    }

    public function useVisitor(VisitorContract $visitor): self
    {
        $this->visitor = $visitor;

        return $this;
    }

    /** @throws InvalidViewable */
    protected function viewable(): Viewable
    {
        return $this->viewable ?? throw InvalidViewable::missing();
    }

    /** @throws InvalidReturning */
    protected function guardReturning(string $method): void
    {
        if ($this->returning) {
            throw InvalidReturning::onlyCounted($method);
        }
    }

    /** @throws InvalidViewable */
    protected function newAttempt(): ViewAttempt
    {
        return new ViewAttempt(
            viewable: $this->viewable(),
            visitor: $this->visitor,
            collection: $this->collection,
            cooldown: $this->cooldown,
            queue: $this->queue,
            viewer: $this->viewer,
            context: $this->context,
        );
    }

    /** @throws InvalidViewable */
    protected function among(?string $class): ?Viewable
    {
        if ($class === null) {
            return null;
        }

        $model = Container::getInstance()->make($class);

        if (! $model instanceof Viewable) {
            throw InvalidViewable::classDoesNotImplementViewable($class);
        }

        return $model;
    }

    protected function query(): ViewsQuery
    {
        return new ViewsQuery($this->period, $this->collection, $this->unique, $this->timezone, $this->viewer, $this->rollup);
    }

    protected function resolveLifetime(DateTimeInterface|int $lifetime): CarbonInterface
    {
        if (is_int($lifetime)) {
            return Carbon::now()->addMinutes($lifetime);
        }

        return Carbon::instance($lifetime);
    }
}
