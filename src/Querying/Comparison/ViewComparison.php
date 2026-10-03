<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Querying\Comparison;

use CyrildeWit\EloquentViewable\Support\Period;
use Illuminate\Contracts\Support\Arrayable;
use JsonSerializable;

/**
 * A view count next to the count of the period before it. The percentage is
 * rounded to one decimal and null when there were no views before, since
 * growth from nothing has no percentage.
 *
 * @implements Arrayable<string, int|float|null>
 */
final readonly class ViewComparison implements Arrayable, JsonSerializable
{
    public int $delta;

    public ?float $percent;

    private function __construct(
        public int $current,
        public int $previous,
        public Period $currentPeriod,
        public Period $previousPeriod,
    ) {
        $this->delta = $current - $previous;
        $this->percent = $previous === 0 ? null : round($this->delta / $previous * 100, 1);
    }

    public static function between(int $current, int $previous, Period $currentPeriod, Period $previousPeriod): self
    {
        return new self($current, $previous, $currentPeriod, $previousPeriod);
    }

    /** @return array{current: int, previous: int, delta: int, percent: float|null} */
    public function toArray(): array
    {
        return [
            'current' => $this->current,
            'previous' => $this->previous,
            'delta' => $this->delta,
            'percent' => $this->percent,
        ];
    }

    /** @return array{current: int, previous: int, delta: int, percent: float|null} */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
