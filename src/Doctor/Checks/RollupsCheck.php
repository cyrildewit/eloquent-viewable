<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Doctor\Checks;

use Carbon\CarbonImmutable;
use CyrildeWit\EloquentViewable\Doctor\Contracts\Check;
use CyrildeWit\EloquentViewable\Doctor\Data\Finding;
use CyrildeWit\EloquentViewable\Querying\Rollups\RollupPolicy;
use CyrildeWit\EloquentViewable\Querying\Rollups\RollupState;
use CyrildeWit\EloquentViewable\Querying\Rollups\UnfoldedViews;
use Generator;

/**
 * Checks that every rollup counts the views it should. Anonymising and
 * pruning wait for views a rollup should count but does not, so such a
 * rollup stops retention until a refold has folded them.
 */
class RollupsCheck implements Check
{
    public function __construct(
        protected RollupPolicy $rollups,
        protected RollupState $state,
        protected UnfoldedViews $unfolded,
    ) {}

    public function name(): string
    {
        return 'Rollups';
    }

    /** @return Generator<int, Finding> */
    public function run(): Generator
    {
        if (! $this->rollups->isEnabled()) {
            yield Finding::skipped('No rollup is configured in `retention.rollups`.');

            return;
        }

        if (! $this->state->installed()) {
            yield Finding::skipped('Nothing to check before the rollup migrations have run.');

            return;
        }

        $findings = $this->unfolded();

        if ($findings === []) {
            yield Finding::pass('Every rollup counts the views it keeps.');

            return;
        }

        yield from $findings;
    }

    /** @return list<Finding> */
    protected function unfolded(): array
    {
        $findings = [];

        foreach ($this->rollups->definitions() as $definition) {
            $snapshot = $this->state->snapshot($definition->name);
            $oldest = $this->unfolded->oldest($definition, $snapshot);
            $start = $this->unfolded->start($definition, $snapshot);

            if (! $oldest instanceof CarbonImmutable || ! $start instanceof CarbonImmutable) {
                continue;
            }

            $findings[] = Finding::warning(
                "The `{$definition->name}` rollup starts at {$start->toDateString()}, but the views table holds views from {$oldest->toDateString()} that it does not count, so anonymising and pruning wait for them.",
                "Run `php artisan views:rollup --rollup={$definition->name} --from={$oldest->toDateString()}` to fold them.",
            );
        }

        return $findings;
    }
}
