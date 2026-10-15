<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Support;

use Carbon\CarbonInterface;
use Carbon\CarbonInterval;

/**
 * A duration is a length of time in the period shorthand, such as `30d` or
 * `2y`. Unlike a period, a duration has no anchor. It counts back from the
 * date it is given.
 */
final readonly class Duration
{
    public function __construct(
        public PeriodInterval $interval,
        public int $value,
    ) {}

    public static function tryParse(string $duration): ?self
    {
        if (preg_match('/^([1-9]\d*)([a-z]+)$/', $duration, $matches) !== 1) {
            return null;
        }

        $interval = PeriodInterval::fromShorthand($matches[2]);

        if (! $interval instanceof PeriodInterval) {
            return null;
        }

        return new self($interval, (int) $matches[1]);
    }

    public function before(CarbonInterface $dateTime): CarbonInterface
    {
        return $this->interval->subtract($dateTime, $this->value);
    }

    public function isLongerThan(self $other, CarbonInterface $from): bool
    {
        return $this->before($from) < $other->before($from);
    }

    public function toInterval(): CarbonInterval
    {
        return match ($this->interval) {
            PeriodInterval::Seconds => CarbonInterval::seconds($this->value),
            PeriodInterval::Minutes => CarbonInterval::minutes($this->value),
            PeriodInterval::Hours => CarbonInterval::hours($this->value),
            PeriodInterval::Days => CarbonInterval::days($this->value),
            PeriodInterval::Weeks => CarbonInterval::weeks($this->value),
            PeriodInterval::Months => CarbonInterval::months($this->value),
            PeriodInterval::Years => CarbonInterval::years($this->value),
        };
    }

    public function shorthand(): string
    {
        return "{$this->value}{$this->interval->shorthand()}";
    }
}
