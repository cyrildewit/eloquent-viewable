<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Querying\Series;

use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use CyrildeWit\EloquentViewable\Querying\Exceptions\InvalidInterval;
use CyrildeWit\EloquentViewable\Support\Granularity;
use CyrildeWit\EloquentViewable\Support\Period;
use Illuminate\Support\Collection;
use IteratorAggregate;
use Traversable;

/**
 * A gap-filled series of view counts over a period, one bucket per
 * granularity step. Buckets are calendar-aligned, so the first bucket may
 * start before the period and the last may end after it.
 *
 * @implements IteratorAggregate<int, Bucket>
 */
final readonly class ViewSeries implements IteratorAggregate
{
    private const string LABEL_FORMAT = 'Y-m-d H:i:s';

    /** @param  Collection<int, Bucket>  $intervals */
    private function __construct(
        public Collection $intervals,
        public Granularity $granularity,
        public Period $period,
    ) {}

    /**
     * Build the series from sparse counts keyed by bucket start label, filling
     * every bucket without views with a count of zero. A null period end walks
     * up to now.
     *
     * @param  array<string, int>  $counts
     *
     * @throws InvalidInterval
     */
    public static function fill(Period $period, Granularity $granularity, array $counts): self
    {
        $startDateTime = $period->getStartDateTime();

        if (! $startDateTime instanceof CarbonInterface) {
            throw InvalidInterval::periodWithoutStartDateTime();
        }

        $start = self::toNaiveClock($granularity->floor($startDateTime));
        $end = self::toNaiveClock($period->getEndDateTime() ?? Carbon::now());

        /** @var Collection<int, Bucket> $intervals */
        $intervals = new Collection;

        for ($cursor = $start; $cursor < $end; $cursor = $granularity->add($cursor, 1)) {
            $label = $cursor->format(self::LABEL_FORMAT);

            $intervals->push(new Bucket(
                start: self::toLocalClock($label),
                end: self::toLocalClock($granularity->add($cursor, 1)->format(self::LABEL_FORMAT)),
                count: $counts[$label] ?? 0,
            ));
        }

        return new self($intervals, $granularity, $period);
    }

    /**
     * The sum of every bucket, equal to the plain count over the same period.
     */
    public function total(): int
    {
        return $this->intervals->sum(static fn (Bucket $bucket): int => $bucket->count);
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

    /**
     * A label read back in the application timezone, the zone the stored
     * values and the caller both use.
     */
    private static function toLocalClock(string $label): CarbonImmutable
    {
        return CarbonImmutable::parse($label, date_default_timezone_get());
    }
}
