<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Querying\Frequency;

use Illuminate\Contracts\Support\Arrayable;
use JsonSerializable;

/**
 * How many visitors viewed on one day, on two, and so on, up to a cap that
 * gathers everyone at or above it, such as `'3+'`. A new visitor viewed on
 * one day and a returning visitor on two or more. The share is rounded to
 * three decimals and null when there were no visitors.
 *
 * @implements Arrayable<int|string, int>
 */
final readonly class VisitFrequency implements Arrayable, JsonSerializable
{
    /** @param  array<int|string, int>  $buckets */
    private function __construct(
        private array $buckets,
    ) {}

    /**
     * @param  array<int, int>  $visitorsByDays
     * @param  int<2, max>  $upTo
     */
    public static function fold(array $visitorsByDays, int $upTo = 3): self
    {
        $buckets = array_fill_keys(range(1, $upTo - 1), 0);
        $buckets["{$upTo}+"] = 0;

        foreach ($visitorsByDays as $days => $visitors) {
            $key = $days >= $upTo ? "{$upTo}+" : $days;

            $buckets[$key] = ($buckets[$key] ?? 0) + $visitors;
        }

        return new self($buckets);
    }

    public function new(): int
    {
        return $this->buckets[1] ?? 0;
    }

    public function returning(): int
    {
        return $this->total() - $this->new();
    }

    public function total(): int
    {
        return array_sum($this->buckets);
    }

    public function returningShare(): ?float
    {
        $total = $this->total();

        if ($total === 0) {
            return null;
        }

        return round($this->returning() / $total, 3);
    }

    /** @return array<int|string, int> */
    public function toArray(): array
    {
        return $this->buckets;
    }

    /** @return array<int|string, int> */
    public function jsonSerialize(): array
    {
        return $this->buckets;
    }
}
