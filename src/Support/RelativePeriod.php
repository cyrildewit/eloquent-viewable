<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Support;

use Carbon\CarbonInterface;

/**
 * What a relative period was asked for, kept so its cache signature does
 * not drift as the wall clock moves.
 *
 * @internal
 */
final readonly class RelativePeriod
{
    public function __construct(
        public PeriodAnchor $anchor,
        public PeriodInterval $interval,
        public int $value,
        public ?Timezone $timezone = null,
    ) {}

    public function startDateTime(): CarbonInterface
    {
        return $this->interval->subtract($this->anchor->dateTime($this->timezone), $this->value);
    }

    public function signature(): string
    {
        $signature = "{$this->anchor->value}{$this->value}{$this->interval->value}";

        return $this->timezone instanceof Timezone ? "{$signature}@{$this->timezone->getName()}" : $signature;
    }
}
