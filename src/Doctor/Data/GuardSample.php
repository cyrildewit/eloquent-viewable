<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Doctor\Data;

/**
 * How many attempts were recorded and how many each guard refused, over the
 * days sampled.
 */
class GuardSample
{
    /**
     * The refusals are keyed by the class of the guard.
     *
     * @param  array<string, int>  $refused
     */
    public function __construct(
        public int $days,
        public int $recorded,
        public array $refused,
    ) {}

    public function attempts(): int
    {
        return $this->recorded + array_sum($this->refused);
    }

    public function refusedBy(string $guard): int
    {
        return $this->refused[$guard] ?? 0;
    }

    /**
     * The share of attempts the guard refused, from 0 to 1.
     */
    public function share(string $guard): float
    {
        $attempts = $this->attempts();

        if ($attempts === 0) {
            return 0.0;
        }

        return $this->refusedBy($guard) / $attempts;
    }
}
