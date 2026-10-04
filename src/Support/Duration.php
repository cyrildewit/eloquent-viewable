<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Support;

use Carbon\CarbonInterface;

/**
 * A length of time in the shorthand periods are parsed from, such as `30d`,
 * `12h` or `2y`. Unlike a period it is not anchored, it counts back from
 * whatever moment it is handed.
 */
final readonly class Duration
{
    public function __construct(
        public PeriodInterval $interval,
        public int $value,
    ) {}

    /**
     * Null when the string is not a shorthand of a positive amount.
     */
    public static function tryParse(string $duration): ?self
    {
        if (preg_match('/^([1-9]\d*)([a-z]+)$/', $duration, $matches) !== 1) {
            return null;
        }

        $interval = PeriodInterval::fromShorthand($matches[2]);

        return $interval instanceof PeriodInterval ? new self($interval, (int) $matches[1]) : null;
    }

    public function before(CarbonInterface $dateTime): CarbonInterface
    {
        return $this->interval->subtract($dateTime, $this->value);
    }

    /**
     * Measured back from the same moment, so `30d` is longer than `4w`.
     */
    public function isLongerThan(self $other, CarbonInterface $from): bool
    {
        return $this->before($from) < $other->before($from);
    }

    public function shorthand(): string
    {
        return "{$this->value}{$this->interval->shorthand()}";
    }
}
