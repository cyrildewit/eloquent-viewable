<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable;

use Carbon\Carbon;
use Carbon\CarbonInterface;
use CyrildeWit\EloquentViewable\Contracts\Viewable;
use CyrildeWit\EloquentViewable\Exceptions\InvalidPeriod;
use CyrildeWit\EloquentViewable\Exceptions\InvalidTimezone;
use CyrildeWit\EloquentViewable\Exceptions\InvalidViewable;
use CyrildeWit\EloquentViewable\Querying\Comparison\ViewComparison;
use CyrildeWit\EloquentViewable\Querying\Exceptions\InvalidInterval;
use CyrildeWit\EloquentViewable\Querying\Exceptions\InvalidLimit;
use CyrildeWit\EloquentViewable\Querying\Ranking\Ranking;
use CyrildeWit\EloquentViewable\Querying\Reader;
use CyrildeWit\EloquentViewable\Querying\Series\ViewSeries;
use CyrildeWit\EloquentViewable\Recording\Contracts\ViewStore;
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

    /** @var array<string, mixed>|null */
    protected ?array $context = null;

    public function __construct(
        protected VisitorContract $visitor,
        protected Recorder $recorder,
        protected Reader $reader,
        protected ViewStore $store,
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
     * @throws InvalidLimit
     * @throws InvalidViewable
     */
    public function top(int $limit = 10): Ranking
    {
        return $this->reader->top($this->viewable, $this->query(), $limit, $this->cacheLifetime);
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
        $this->store->forget($this->viewable());
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

    /** @param  array<string, mixed>|null  $context */
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

    protected function query(): ViewsQuery
    {
        return new ViewsQuery($this->period, $this->collection, $this->unique, $this->timezone, $this->viewer);
    }

    protected function resolveLifetime(DateTimeInterface|int $lifetime): CarbonInterface
    {
        if (is_int($lifetime)) {
            return Carbon::now()->addMinutes($lifetime);
        }

        return Carbon::instance($lifetime);
    }
}
