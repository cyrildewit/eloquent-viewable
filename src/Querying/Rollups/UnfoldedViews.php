<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Querying\Rollups;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use CyrildeWit\EloquentViewable\Models\View;
use Illuminate\Database\Query\Builder;

/**
 * Finds the views a rollup should count but no tier of it holds: the views
 * before where its rows start, other than those every tier has expired. A
 * run that carries on from where a tier was folded never goes back for them,
 * so only a refold from before them does.
 *
 * @internal
 */
final readonly class UnfoldedViews
{
    public function __construct(
        private View $view,
        private RollupPolicy $policy,
    ) {}

    /**
     * When `$before` is given, only a view before it is looked for.
     */
    public function oldest(RollupDefinition $definition, Snapshot $snapshot, ?CarbonInterface $before = null): ?CarbonImmutable
    {
        $start = $this->start($definition, $snapshot);

        if (! $start instanceof CarbonImmutable) {
            return null;
        }

        $until = $before instanceof CarbonInterface ? $start->min($before) : $start;
        $kept = $this->kept($definition);

        if ($kept instanceof CarbonImmutable && $kept >= $until) {
            return null;
        }

        $oldest = $this->view
            ->newQuery()
            ->toBase()
            ->when($kept, fn (Builder $query, CarbonImmutable $kept): Builder => $query->where('viewed_at', '>=', $kept))
            ->where('viewed_at', '<', $until)
            ->min('viewed_at');

        if (! is_string($oldest)) {
            return null;
        }

        return CarbonImmutable::parse($oldest);
    }

    /**
     * Where the rows of the rollup start, the earliest start of its tiers, or
     * null while a tier was never folded.
     */
    public function start(RollupDefinition $definition, Snapshot $snapshot): ?CarbonImmutable
    {
        $start = null;

        foreach ($definition->tiers() as $tier) {
            $since = $snapshot->since($tier);

            if (! $since instanceof CarbonImmutable) {
                return null;
            }

            $start = $start?->min($since) ?? $since;
        }

        return $start;
    }

    /**
     * Where the kept buckets of the rollup start, the earliest of its tiers,
     * or null while a tier is kept for good.
     */
    private function kept(RollupDefinition $definition): ?CarbonImmutable
    {
        $now = CarbonImmutable::now();
        $kept = null;

        foreach ($definition->tiers() as $tier) {
            $from = $definition->keptFrom($tier, $now, $this->policy->timezone);

            if (! $from instanceof CarbonImmutable) {
                return null;
            }

            $kept = $kept?->min($from) ?? $from;
        }

        return $kept;
    }
}
