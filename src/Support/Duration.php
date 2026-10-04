<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Support;

use Carbon\CarbonInterface;

/**
 * A duration is a length of time in the period shorthand, such as `30d` or
 * `2y`. Unlike a period it is not anchored: it counts back from the moment it
 * is handed.
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

    public function shorthand(): string
    {
        return "{$this->value}{$this->interval->shorthand()}";
    }
}
