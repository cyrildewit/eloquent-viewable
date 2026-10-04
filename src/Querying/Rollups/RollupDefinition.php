<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Querying\Rollups;

use CyrildeWit\EloquentViewable\Models\View;
use CyrildeWit\EloquentViewable\Support\Duration;
use Illuminate\Database\Eloquent\Builder;

/** @internal */
final readonly class RollupDefinition
{
    /**
     * @param  array<string, ?Duration>  $tiers  keyed by tier, coarse to fine
     * @param  list<Grouping>  $groupings
     */
    public function __construct(
        public string $name,
        private array $tiers,
        public array $groupings,
        private ?Rollup $custom = null,
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
        return $this->custom?->dimension();
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
