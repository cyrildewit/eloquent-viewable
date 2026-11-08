<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Doctor\Checks;

use Carbon\CarbonImmutable;
use CyrildeWit\EloquentViewable\Dimensions\DimensionRegistry;
use CyrildeWit\EloquentViewable\Doctor\Contracts\Check;
use CyrildeWit\EloquentViewable\Doctor\Data\Finding;
use CyrildeWit\EloquentViewable\Models\View;
use CyrildeWit\EloquentViewable\Querying\Rollups\RollupDefinition;
use CyrildeWit\EloquentViewable\Querying\Rollups\RollupPolicy;
use CyrildeWit\EloquentViewable\Querying\Rollups\RollupState;
use CyrildeWit\EloquentViewable\Querying\Rollups\Tier;
use Generator;

/**
 * Checks what the configured dimensions need beyond their columns: a database
 * that can rank values for the cap of the rollups, and rollups that hold the
 * history that a count by dimension reads. SchemaCheck covers the columns.
 */
class DimensionsCheck implements Check
{
    /** The hour rows of a dimension's rollup from which dropping them is advised. */
    public const int LargeHourTier = 100_000;

    public function __construct(
        protected DimensionRegistry $dimensions,
        protected RollupPolicy $rollups,
        protected RollupState $state,
        protected View $view,
    ) {}

    public function name(): string
    {
        return 'Dimensions';
    }

    /** @return Generator<int, Finding> */
    public function run(): Generator
    {
        if ($this->dimensions->isEmpty()) {
            yield Finding::skipped('No dimension is listed in `dimensions.definitions`.');

            return;
        }

        $findings = [
            ...$this->windowFunctions(),
            ...$this->foldedPersonal(),
            ...$this->lateRollups(),
            ...$this->largeHourTiers(),
        ];

        if ($findings === []) {
            yield Finding::pass('Every dimension can be recorded, folded and counted.');

            return;
        }

        yield from $findings;
    }

    /**
     * The cap ranks the values of a bucket with a window function, which
     * MySQL only has from 8.0 on.
     *
     * @return list<Finding>
     */
    protected function windowFunctions(): array
    {
        $capped = array_filter($this->folded(), static fn (RollupDefinition $definition): bool => $definition->maxValues() !== null);

        if ($capped === []) {
            return [];
        }

        if ($this->driverName() !== 'mysql') {
            return [];
        }

        $version = $this->serverVersion();

        if (version_compare($version, '8.0', '>=')) {
            return [];
        }

        return [Finding::failure(
            "Rollups fold dimensions with a cap, which needs MySQL 8.0 or newer, and the database runs {$version}.",
            'Upgrade MySQL, or give every folded dimension `maxValues` of null to keep all its values.',
        )];
    }

    /**
     * Rollup rows carry no visitor, so neither anonymising nor erasing a
     * person reaches the values of a personal dimension folded into them.
     *
     * @return list<Finding>
     */
    protected function foldedPersonal(): array
    {
        $findings = [];

        foreach (array_keys($this->folded()) as $name) {
            if ($this->dimensions->find($name)?->personal() !== true) {
                continue;
            }

            $findings[] = Finding::warning(
                "The `{$name}` dimension is marked personal but folded into rollups, where anonymising and erasing a person never reach its values.",
                "Remove `{$name}` from `retention.rollups.dimensions`, or give it `'personal' => false` if its values identify no one.",
            );
        }

        return $findings;
    }

    /**
     * A dimension folded after the built-in rollup has no rows before its
     * first fold, so its history there is read from the views table while
     * the views are kept.
     *
     * @return list<Finding>
     */
    protected function lateRollups(): array
    {
        if (! $this->state->installed()) {
            return [];
        }

        $since = $this->state->snapshot(RollupPolicy::BuiltIn)->origin;

        if (! $since instanceof CarbonImmutable) {
            return [];
        }

        $findings = [];

        foreach ($this->folded() as $name => $definition) {
            $origin = $this->state->snapshot($definition->name)->origin;

            if (! $origin instanceof CarbonImmutable) {
                continue;
            }

            if ($origin <= $since) {
                continue;
            }

            $findings[] = Finding::advice(
                "The `{$name}` dimension is folded from {$origin->toDateString()}, the views it counts from {$since->toDateString()}, so its rollup holds nothing before {$origin->toDateString()}.",
                "A count by `{$name}` over that time reads the views table while it still holds those views, and refuses once they are pruned.",
            );
        }

        return $findings;
    }

    /**
     * The hour rows of a dimension split each hour by every value, so they
     * outgrow the rest of the rollups, while the hourly totals that trending
     * and spikes read come from the built-in rollup.
     *
     * @return list<Finding>
     */
    protected function largeHourTiers(): array
    {
        if (! $this->state->installed()) {
            return [];
        }

        $findings = [];

        foreach ($this->folded() as $name => $definition) {
            $coarser = array_reverse(array_filter($definition->tiers(), static fn (Tier $tier): bool => $tier !== Tier::Hour));

            if (! in_array(Tier::Hour, $definition->tiers(), true) || $coarser === []) {
                continue;
            }

            $rows = $this->view->getConnection()->table($this->rollups->table)
                ->where('rollup', $definition->name)
                ->where('tier', Tier::Hour->value)
                ->count();

            if ($rows < $this->largeHourTier()) {
                continue;
            }

            $count = number_format($rows);
            $tiers = implode("', '", array_map(static fn (Tier $tier): string => $tier->value, $coarser));

            $findings[] = Finding::advice(
                "The hour tier of the `{$name}` dimension holds {$count} rows.",
                "Unless you read `{$name}` by the hour, list `'{$name}' => ['tiers' => ['{$tiers}']]` under `retention.rollups.dimensions`. The next `views:maintain` drops its hour rows.",
            );
        }

        return $findings;
    }

    protected function largeHourTier(): int
    {
        return self::LargeHourTier;
    }

    /** @return array<string, RollupDefinition> */
    protected function folded(): array
    {
        $folded = [];

        foreach (array_keys($this->dimensions->all()) as $name) {
            $definition = $this->rollups->forDimension($name);

            if ($definition instanceof RollupDefinition) {
                $folded[$name] = $definition;
            }
        }

        return $folded;
    }

    protected function driverName(): string
    {
        return $this->view->getConnection()->getDriverName();
    }

    protected function serverVersion(): string
    {
        return $this->view->getConnection()->getServerVersion();
    }
}
