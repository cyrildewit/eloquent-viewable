<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Querying\Growth;

use Illuminate\Contracts\Support\Arrayable;
use JsonSerializable;

/**
 * A baseline holds a count and the counts of the windows it is compared
 * with, and says how far the count lies from what those windows lead to
 * expect.
 *
 * @implements Arrayable<string, int|float|list<int>>
 */
final readonly class Baseline implements Arrayable, JsonSerializable
{
    public float $mean;

    public float $stddev;

    /**
     * @param  int  $current  the count of the window itself
     * @param  non-empty-list<int>  $references  the counts of the windows it is compared with, the closest first
     */
    public function __construct(
        public int $current,
        public array $references,
    ) {
        $this->mean = array_sum($references) / count($references);
        $this->stddev = sqrt(array_sum(array_map(fn (int $count): float => ($count - $this->mean) ** 2, $references)) / count($references));
    }

    /**
     * Read how many deviations the count lies above the mean, below it when
     * negative. The deviation is at least the square root of the mean, the
     * noise a count of that size has anyway, and at least 1, so a baseline
     * that never moved does not turn one extra view into a spike.
     */
    public function zScore(): float
    {
        return ($this->current - $this->mean) / max($this->stddev, sqrt($this->mean), 1.0);
    }

    /**
     * Read how many times the mean the count is. A mean below one counts as
     * one, so growth from nothing stays finite.
     */
    public function ratio(): float
    {
        return $this->current / max($this->mean, 1.0);
    }

    /** @return array{current: int, references: list<int>, mean: float, stddev: float, z_score: float, ratio: float} */
    public function toArray(): array
    {
        return [
            'current' => $this->current,
            'references' => $this->references,
            'mean' => $this->mean,
            'stddev' => $this->stddev,
            'z_score' => $this->zScore(),
            'ratio' => $this->ratio(),
        ];
    }

    /** @return array{current: int, references: list<int>, mean: float, stddev: float, z_score: float, ratio: float} */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
