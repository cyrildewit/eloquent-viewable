<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Querying\Rollups;

use CyrildeWit\EloquentViewable\Models\View;
use CyrildeWit\EloquentViewable\Support\Duration;
use Illuminate\Database\Eloquent\Builder;

/**
 * A rollup to fold and read: the built-in one, a custom one, or the rollup of
 * a dimension, which only keeps rows per value because the built-in rollup
 * already keeps the totals.
 *
 * @internal
 */
final readonly class RollupDefinition
{
    /**
     * @param  array<string, ?Duration>  $tiers  keyed by tier, coarse to fine
     * @param  list<Grouping>  $groupings
     * @param  ?string  $dimension  the column or JSON path of a dimension's rollup
     * @param  ?int  $maxValues  how many values a bucket keeps before the rest is folded into `other`
     */
    public function __construct(
        public string $name,
        private array $tiers,
        public array $groupings,
        private ?Rollup $custom = null,
        private ?string $dimension = null,
        private ?int $maxValues = null,
    ) {}

    /** @return list<Tier> */
    public function tiers(): array
    {
        return array_map(Tier::from(...), array_keys($this->tiers));
    }

    public function keep(Tier $tier): ?Duration
    {
        return $this->tiers[$tier->value] ?? null;
    }

    public function coarserThan(Tier $tier): ?Tier
    {
        $coarser = null;

        foreach ($this->tiers() as $candidate) {
            if ($candidate->isCoarserThan($tier)) {
                $coarser = $candidate;
            }
        }

        return $coarser;
    }

    public function keeps(Grouping $grouping): bool
    {
        return in_array($grouping, $this->groupings, true);
    }

    public function dimension(): ?string
    {
        return $this->dimension ?? $this->custom?->dimension();
    }

    /**
     * Whether only the rows per value are kept, as for a dimension's rollup.
     */
    public function dimensionOnly(): bool
    {
        return $this->dimension !== null;
    }

    public function maxValues(): ?int
    {
        return $this->maxValues;
    }

    /** @param  Builder<View>  $views */
    public function filter(Builder $views): void
    {
        $this->custom?->filter($views);
    }

    public function rollup(): ?Rollup
    {
        return $this->custom;
    }
}
