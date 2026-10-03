<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Querying\Series;

use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use CyrildeWit\EloquentViewable\Querying\Exceptions\InvalidInterval;
use CyrildeWit\EloquentViewable\Support\Granularity;
use CyrildeWit\EloquentViewable\Support\Period;
use CyrildeWit\EloquentViewable\Support\Timezone;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Support\Collection;
use IteratorAggregate;
use JsonSerializable;
use Traversable;

/**
 * A gap-filled series of view counts over a period, one bucket per
 * granularity step. Buckets are calendar-aligned on the clock of the
 * timezone, so the first bucket may start before the period and the last may
 * end after it.
 *
 * @implements Arrayable<string, mixed>
 * @implements IteratorAggregate<int, Bucket>
 */
final readonly class ViewSeries implements Arrayable, IteratorAggregate, JsonSerializable
{
    private const string LABEL_FORMAT = 'Y-m-d H:i:s';

    /** @param  Collection<int, Bucket>  $intervals */
    private function __construct(
        public Collection $intervals,
        public Granularity $granularity,
        public Period $period,
        public Timezone $timezone,
    ) {}

    /**
     * Build the series from sparse counts keyed by bucket start label, filling
     * every bucket without views with a count of zero. A null period end walks
     * up to now. The labels are wall clocks of the timezone, the application
     * timezone unless one is given, and so are the bucket bounds.
     *
     * @param  array<string, int>  $counts
     *
     * @throws InvalidInterval
     */
    public static function fill(Period $period, Granularity $granularity, array $counts, ?Timezone $timezone = null): self
    {
        $startDateTime = $period->getStartDateTime();

        if (! $startDateTime instanceof CarbonInterface) {
            throw InvalidInterval::periodWithoutStartDateTime();
        }

        $timezone ??= Timezone::application();

        $start = self::toNaiveClock($granularity->floor($startDateTime->avoidMutation()->setTimezone($timezone)));
        $end = self::toNaiveClock(($period->getEndDateTime() ?? Carbon::now())->avoidMutation()->setTimezone($timezone));

        /** @var Collection<int, Bucket> $intervals */
        $intervals = new Collection;

        for ($cursor = $start; $cursor < $end; $cursor = $granularity->add($cursor, 1)) {
            $label = $cursor->format(self::LABEL_FORMAT);

            $intervals->push(new Bucket(
                start: self::toLocalClock($label, $timezone),
                end: self::toLocalClock($granularity->add($cursor, 1)->format(self::LABEL_FORMAT), $timezone),
                count: $counts[$label] ?? 0,
                label: $cursor->format($granularity->labelFormat()),
            ));
        }

        return new self($intervals, $granularity, $period, $timezone);
    }

    /**
     * The sum of every bucket, equal to the plain count over the same period.
     */
    public function total(): int
    {
        return $this->intervals->sum(static fn (Bucket $bucket): int => $bucket->count);
    }

    /** @return list<string> */
    public function labels(): array
    {
        return array_values($this->intervals->map(static fn (Bucket $bucket): string => $bucket->label)->all());
    }

    /** @return list<int> */
    public function values(): array
    {
        return array_values($this->intervals->map(static fn (Bucket $bucket): int => $bucket->count)->all());
    }

    /**
     * The earliest bucket wins a tie.
     */
    public function peak(): ?Bucket
    {
        return $this->intervals->reduce(
            static fn (?Bucket $peak, Bucket $bucket): Bucket => ! $peak instanceof Bucket || $bucket->count > $peak->count ? $bucket : $peak,
        );
    }

    public function average(): float
    {
        return $this->intervals->isEmpty() ? 0.0 : $this->total() / $this->intervals->count();
    }

    /** @return array{granularity: string, total: int, labels: list<string>, values: list<int>} */
    public function toArray(): array
    {
        return [
            'granularity' => $this->granularity->value,
            'total' => $this->total(),
            'labels' => $this->labels(),
            'values' => $this->values(),
        ];
    }

    /** @return array{granularity: string, total: int, labels: list<string>, values: list<int>} */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    /** @return Traversable<int, Bucket> */
    public function getIterator(): Traversable
    {
        return $this->intervals->getIterator();
    }

    /**
     * The wall clock reinterpreted as UTC. Walking a cursor without timezone
     * semantics emits exactly one label per stored wall-clock hour, even
     * across a DST transition where two real hours share a label.
     */
    private static function toNaiveClock(CarbonInterface $dateTime): CarbonImmutable
    {
        return CarbonImmutable::parse($dateTime->format(self::LABEL_FORMAT), 'UTC');
    }

    private static function toLocalClock(string $label, Timezone $timezone): CarbonImmutable
    {
        return CarbonImmutable::parse($label, $timezone);
    }
}
