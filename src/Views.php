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
use CyrildeWit\EloquentViewable\Querying\Cache\CacheVersions;
use CyrildeWit\EloquentViewable\Querying\Comparison\ViewComparison;
use CyrildeWit\EloquentViewable\Querying\Exceptions\InvalidDecay;
use CyrildeWit\EloquentViewable\Querying\Exceptions\InvalidInterval;
use CyrildeWit\EloquentViewable\Querying\Exceptions\InvalidLimit;
use CyrildeWit\EloquentViewable\Querying\Exceptions\UnsupportedBySource;
use CyrildeWit\EloquentViewable\Querying\Ranking\DecayCurve;
use CyrildeWit\EloquentViewable\Querying\Ranking\Ranking;
use CyrildeWit\EloquentViewable\Querying\Reader;
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

    public function count(): int
    {
        return $this->reader->count($this->viewable(), $this->query(), $this->cacheLifetime);
    }

    /** @throws InvalidPeriod */
    public function compare(): ViewComparison
    {
        return $this->reader->compare($this->viewable(), $this->query(), $this->cacheLifetime);
    }

    /**
     * @return Collection<int|string, int>
     *
     * @throws InvalidViewable
     */
    public function counts(): Collection
    {
        $viewables = $this->viewables ?? throw InvalidViewable::missingSet();

        return new Collection($this->reader->countMany($viewables, $this->query(), $this->cacheLifetime));
    }

    /** @throws InvalidInterval */
    public function countByInterval(Granularity $granularity): ViewSeries
    {
        return $this->reader->countByInterval($this->viewable(), $this->query(), $granularity, $this->cacheLifetime);
    }

    /** @return array<string, int> */
    public function countByCollection(): array
    {
        return $this->reader->countByCollection($this->viewable(), $this->query(), $this->cacheLifetime);
    }

    /**
     * @return array<string, int>
     *
     * @throws UnknownRollup
     */
    public function countByDimension(): array
    {
        $dimension = $this->rollup?->dimension();

        if ($dimension === null) {
            throw UnknownRollup::withoutDimension($this->rollup?->name);
        }

        return $this->reader->countByDimension($this->viewable(), $this->query(), $dimension, $this->cacheLifetime);
    }

    /**
     * @throws InvalidLimit
     * @throws InvalidViewable
     */
    public function top(int $limit = 10): Ranking
    {
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
     * @throws InvalidViewable
     * @throws UnsupportedBySource
     */
    public function trending(int $limit = 10, ?CarbonInterval $halfLife = null, ?DecayCurve $curve = null): Ranking
    {
        return $this->reader->trending($this->viewable, $this->query(), $limit, $halfLife, $curve, $this->cacheLifetime);
    }

    /**
     * What the visitors of the viewable also viewed, ranked by how many of
     * them did. Pass a model class to rank only models of that class.
     *
     * @param  class-string<Model&Viewable>|null  $among
     *
     * @throws InvalidConfiguration
     * @throws InvalidLimit
     * @throws InvalidViewable
     * @throws InvalidViewer
     * @throws UnsupportedBySource
     */
    public function alsoViewed(int $limit = 10, ?string $among = null): Ranking
    {
        return $this->reader->alsoViewed($this->viewable(), $this->among($among), $this->query(), $limit, $this->cacheLifetime);
    }

    /** @throws RecordingFailed */
    public function record(): bool
    {
        return $this->attempt()->recorded;
    }

    /** @throws RecordingFailed */
    public function attempt(): RecordResult
    {
        return $this->recorder->record(new ViewAttempt(
            viewable: $this->viewable(),
            visitor: $this->visitor,
            collection: $this->collection,
            cooldown: $this->cooldown,
            queue: $this->queue,
            viewer: $this->viewer,
            context: $this->context,
        ));
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
