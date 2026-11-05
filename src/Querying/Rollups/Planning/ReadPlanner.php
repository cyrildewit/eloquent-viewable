<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Querying\Rollups\Planning;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Closure;
use CyrildeWit\EloquentViewable\Contracts\FiltersViews;
use CyrildeWit\EloquentViewable\Querying\Exceptions\UnsupportedBySource;
use CyrildeWit\EloquentViewable\Querying\Ranking\Decay;
use CyrildeWit\EloquentViewable\Querying\Rollups\Exceptions\ResolutionUnavailable;
use CyrildeWit\EloquentViewable\Querying\Rollups\Grouping;
use CyrildeWit\EloquentViewable\Querying\Rollups\RollupDefinition;
use CyrildeWit\EloquentViewable\Querying\Rollups\RollupPolicy;
use CyrildeWit\EloquentViewable\Querying\Rollups\RollupState;
use CyrildeWit\EloquentViewable\Querying\Rollups\Tier;
use CyrildeWit\EloquentViewable\Support\DimensionFilter;
use CyrildeWit\EloquentViewable\Support\Granularity;
use CyrildeWit\EloquentViewable\Support\Timezone;
use CyrildeWit\EloquentViewable\Support\ViewsQuery;
use DateTimeZone;
use Illuminate\Database\Eloquent\Model;

/**
 * Decides whether the rollups may answer a read, and which part of its
 * period each tier covers. It needs the policy and the marks of the folds,
 * never a connection, so the decision is checked without a database. Under
 * `strict` a read the rollups cannot answer exactly is refused rather than
 * answered from the views table.
 *
 * @internal
 */
final readonly class ReadPlanner
{
    private Planner $planner;

    public function __construct(
        private RollupPolicy $policy,
        private RollupState $state,
    ) {
        $this->planner = new Planner($policy->timezone);
    }

    /**
     * The rollup that answers the read and the plan that splits its period
     * between the rollups and the views table, or null when the views table
     * answers alone. Under `strict` a read the rollups cannot answer exactly
     * throws instead.
     *
     * @throws ResolutionUnavailable
     */
    public function plan(Grouping $grouping, ViewsQuery $query, ?Granularity $granularity = null, ?string $counted = null): ?PlannedRead
    {
        $definition = $this->definition($grouping, $query, $counted);

        if (! $definition instanceof RollupDefinition) {
            return null;
        }

        $tiers = $definition->tiers();

        if ($granularity instanceof Granularity) {
            $tiers = array_values(array_filter($tiers, static fn (Tier $tier): bool => $tier->fits($granularity)));
        }

        if ($tiers === []) {
            return null;
        }

        $align = $granularity instanceof Granularity ? $this->alignTo($granularity, $this->seriesZone($query)) : null;
        $plan = $this->planWith($definition, $tiers, $query, $align);

        if (! $plan instanceof Plan) {
            return null;
        }

        if ($this->policy->strict) {
            $this->guard($plan, $query, $granularity);
        }

        return new PlannedRead($plan, $definition);
    }

    /**
     * Only the tiers no coarser than the step answer, and for unique visitors
     * only the tier of the step itself, so no visitor is counted twice in a
     * step. With none left the views table answers, or strict refuses.
     *
     * @throws ResolutionUnavailable
     */
    public function trendingPlan(Grouping $grouping, ViewsQuery $query, Decay $decay): ?PlannedRead
    {
        $definition = $this->definition($grouping, $query);

        if (! $definition instanceof RollupDefinition) {
            return null;
        }

        $step = $decay->step();
        $tiers = array_values(array_filter(
            $definition->tiers(),
            static fn (Tier $tier): bool => $query->unique ? $tier->granularity() === $step : $tier->fits($step),
        ));

        if ($tiers === []) {
            if ($this->policy->strict) {
                throw ResolutionUnavailable::trendingStep($step->value);
            }

            return null;
        }

        $plan = $this->planWith($definition, $tiers, $query, $this->alignTo($step, $this->policy->timezone));

        if (! $plan instanceof Plan) {
            return null;
        }

        if ($this->policy->strict && ! $plan->isExact()) {
            throw ResolutionUnavailable::partialBucket();
        }

        return new PlannedRead($plan, $definition);
    }

    /**
     * The plan of the period over the tiers that may answer, or null when
     * the views table answers it all.
     *
     * @param  list<Tier>  $tiers
     * @param  (Closure(CarbonImmutable): CarbonImmutable)|null  $align
     */
    private function planWith(RollupDefinition $definition, array $tiers, ViewsQuery $query, ?Closure $align): ?Plan
    {
        $plan = $this->planner->plan(
            $this->state->snapshot($definition->name),
            $tiers,
            $query->period?->getStartDateTime(),
            $query->period?->getEndDateTime(),
            $query->unique,
            $align,
        );

        return $plan->isRawOnly() ? null : $plan;
    }

    /**
     * Moves the hand-over to the views table onto the edge of a bucket of the
     * granularity on the clock of the zone, so no bucket is summed from both.
     *
     * @return Closure(CarbonImmutable): CarbonImmutable
     */
    private function alignTo(Granularity $granularity, DateTimeZone $zone): Closure
    {
        return static fn (CarbonImmutable $moment): CarbonImmutable => CarbonImmutable::instance($granularity->floor($moment->setTimezone($zone)))
            ->setTimezone($moment->getTimezone());
    }

    /**
     * The rollup the query reads through, unless the rollups cannot answer
     * it: a read narrowed to a viewer, or one by a grouping not kept. A read
     * that narrows or counts by a dimension goes through that dimension's
     * rollup.
     *
     * @throws ResolutionUnavailable
     */
    private function definition(Grouping $grouping, ViewsQuery $query, ?string $counted = null): ?RollupDefinition
    {
        $names = $query->dimensionNames();

        if ($counted !== null && ! in_array($counted, $names, true)) {
            $names[] = $counted;
        }

        if ($names !== []) {
            return $this->dimensionDefinition($grouping, $query, $names);
        }

        $definition = $this->policy->for($query);

        if (! $definition instanceof RollupDefinition) {
            return null;
        }

        if ($query->viewer instanceof Model) {
            return null;
        }

        if (! $definition->keeps($grouping)) {
            return null;
        }

        return $definition;
    }

    /**
     * Only a dimension's own rollup holds its values, so it answers when that
     * dimension is the only one the read names, the read is not narrowed by
     * a custom rollup or a viewer, and unique visitors are not summed across
     * values. Any other read goes to the views table, which only holds the
     * whole period until views are pruned.
     *
     * @param  non-empty-list<string>  $names
     *
     * @throws ResolutionUnavailable
     */
    private function dimensionDefinition(Grouping $grouping, ViewsQuery $query, array $names): ?RollupDefinition
    {
        $definition = count($names) === 1 ? $this->policy->forDimension($names[0]) : null;

        if ($this->answers($definition, $grouping, $query)) {
            return $definition;
        }

        $this->guardViewsTable($query, $names);

        return null;
    }

    /** @phpstan-assert-if-true RollupDefinition $definition */
    private function answers(?RollupDefinition $definition, Grouping $grouping, ViewsQuery $query): bool
    {
        if (! $definition instanceof RollupDefinition) {
            return false;
        }

        if ($query->filter instanceof FiltersViews) {
            return false;
        }

        if ($query->viewer instanceof Model) {
            return false;
        }

        if (! $definition->keeps($grouping)) {
            return false;
        }

        if (! $query->unique) {
            return true;
        }

        return array_all($query->dimensions, static fn (DimensionFilter $filter): bool => count($filter->values) === 1);
    }

    /**
     * Refuses a read the views table can no longer answer for the whole
     * period, rather than count only the views it still holds.
     *
     * @param  non-empty-list<string>  $names
     *
     * @throws ResolutionUnavailable
     */
    private function guardViewsTable(ViewsQuery $query, array $names): void
    {
        $pruned = $this->state->snapshot(RollupPolicy::BuiltIn)->pruned;

        if (! $pruned instanceof CarbonImmutable) {
            return;
        }

        $start = $query->period?->getStartDateTime();

        if ($start instanceof CarbonInterface && $start >= $pruned) {
            return;
        }

        if ($this->policy->strict) {
            throw ResolutionUnavailable::dimensionHistory($names, $pruned);
        }

        throw UnsupportedBySource::dimensionHistory($names, $pruned);
    }

    /** @throws ResolutionUnavailable */
    private function guard(Plan $plan, ViewsQuery $query, ?Granularity $granularity): void
    {
        if (! $plan->isExact()) {
            throw ResolutionUnavailable::partialBucket();
        }

        if (! $granularity instanceof Granularity) {
            $this->guardCount($plan, $query);

            return;
        }

        $zone = $this->seriesZone($query);

        if ($zone->getName() !== $this->policy->timezone->getName()) {
            $this->guardOtherTimezone($plan, $zone);
        }

        if (! $query->unique) {
            return;
        }

        foreach ($plan->rollups() as $segment) {
            if ($segment->tier?->granularity() !== $granularity) {
                throw ResolutionUnavailable::summedUniques();
            }
        }
    }

    /** @throws ResolutionUnavailable */
    private function guardCount(Plan $plan, ViewsQuery $query): void
    {
        if (! $query->unique) {
            return;
        }

        if ($plan->parts($this->policy->timezone) > 1) {
            throw ResolutionUnavailable::summedUniques();
        }
    }

    /**
     * Only hour buckets fit a series in another zone, and only while the zones
     * are whole hours apart.
     *
     * @throws ResolutionUnavailable
     */
    private function guardOtherTimezone(Plan $plan, DateTimeZone $zone): void
    {
        foreach ($plan->rollups() as $segment) {
            if ($segment->tier === Tier::Hour && $this->alignsByTheHour($segment, $zone)) {
                continue;
            }

            throw ResolutionUnavailable::otherTimezone($zone->getName(), $this->policy->timezone->getName());
        }
    }

    private function alignsByTheHour(Segment $segment, DateTimeZone $zone): bool
    {
        return array_all(
            array_filter([$segment->start, $segment->end]),
            fn (CarbonImmutable $moment): bool => ($zone->getOffset($moment) - $this->policy->timezone->getOffset($moment)) % 3600 === 0,
        );
    }

    private function seriesZone(ViewsQuery $query): Timezone
    {
        return $query->timezone ?? Timezone::application();
    }
}
