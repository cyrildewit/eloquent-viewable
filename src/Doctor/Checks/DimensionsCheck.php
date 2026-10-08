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
use Generator;

/**
 * What the dimensions in config need beyond their columns, which the schema
 * check covers: a database that ranks values for the rollups' cap, and
 * rollups that hold the history a count by a dimension reads.
 */
class DimensionsCheck implements Check
{
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
            ...$this->lateRollups(),
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
