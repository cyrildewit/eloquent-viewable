<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Support;

use Carbon\CarbonInterface;

/**
 * What a relative period was asked for, kept so its cache signature and
 * route key do not drift as the wall clock moves. The shift counts how many
 * of its own widths the period was moved back by `Period::previous()`.
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
        public int $shift = 0,
    ) {}

    public function startDateTime(): CarbonInterface
    {
        return $this->interval->subtract($this->anchor->dateTime($this->timezone), $this->value * ($this->shift + 1));
    }

    /**
     * Null until the period is shifted, because an unshifted relative period
     * runs on past now.
     */
    public function endDateTime(): ?CarbonInterface
    {
        if ($this->shift === 0) {
            return null;
        }

        return $this->interval->subtract($this->anchor->dateTime($this->timezone), $this->value * $this->shift);
    }

    public function previous(): self
    {
        return new self($this->anchor, $this->interval, $this->value, $this->timezone, $this->shift + 1);
    }

    public function in(Timezone $timezone): self
    {
        return new self($this->anchor, $this->interval, $this->value, $timezone, $this->shift);
    }

    public function signature(): string
    {
        $signature = "{$this->anchor->value}{$this->value}{$this->interval->value}";

        if ($this->shift !== 0) {
            $signature .= "~{$this->shift}";
        }

        if (! $this->timezone instanceof Timezone) {
            return $signature;
        }

        return "{$signature}@{$this->timezone->getName()}";
    }

    /**
     * Null when the interval is counted from the other anchor, or the period
     * was shifted back, which no shorthand says.
     */
    public function shorthand(): ?string
    {
        if ($this->shift !== 0 || $this->interval->anchor() !== $this->anchor) {
            return null;
        }

        return "{$this->value}{$this->interval->shorthand()}";
    }
}
