<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Querying\Rollups;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use CyrildeWit\EloquentViewable\Contracts\FiltersViews;
use CyrildeWit\EloquentViewable\Dimensions\DimensionRegistry;
use CyrildeWit\EloquentViewable\Exceptions\InvalidConfiguration;
use CyrildeWit\EloquentViewable\Exceptions\InvalidTimezone;
use CyrildeWit\EloquentViewable\Support\Config;
use CyrildeWit\EloquentViewable\Support\Duration;
use CyrildeWit\EloquentViewable\Support\Timezone;
use CyrildeWit\EloquentViewable\Support\ViewsQuery;
use Illuminate\Container\Container;
use Illuminate\Support\Carbon;

final readonly class RollupPolicy
{
    public const string BuiltIn = 'views';

    /**
     * The rollup of a dimension is named after it, behind a prefix no custom
     * rollup can use, such as `views:source`.
     */
    public const string DimensionPrefix = 'views:';

    private const array Tiers = ['hour', 'day', 'month', 'year'];

    private const array Groupings = ['viewable', 'viewable_collection', 'type', 'type_collection'];

    /**
     * @param  list<RollupDefinition>  $definitions
     *
     * @throws InvalidConfiguration
     */
    public function __construct(
        private array $definitions,
        public Timezone $timezone,
        public ?Duration $settle,
        public bool $strict,
        public string $table,
        ?Duration $pruneAfter = null,
    ) {
        $now = Carbon::now();

        foreach ($this->definitions as $definition) {
            $finer = null;

            foreach (array_reverse($definition->tiers()) as $tier) {
                if ($finer instanceof Tier && $this->keptShorter($definition, $tier, $finer, $now)) {
                    throw InvalidConfiguration::coarserTierKeptShorter($definition->name, $tier->value, $finer->value);
                }

                $finer = $tier;
            }
        }

        $coarsest = $this->coarsestTier();

        if ($coarsest instanceof Tier && $pruneAfter instanceof Duration) {
            $closed = $coarsest->granularity()->add($this->settle?->before($now) ?? $now, -1);

            if ($pruneAfter->before($now) > $closed) {
                throw InvalidConfiguration::prunedBeforeFolded($pruneAfter->shorthand(), $coarsest->value, $this->settle?->shorthand() ?? 'null');
            }
        }
    }

    /**
     * @throws InvalidConfiguration
     * @throws InvalidTimezone
     */
    public static function fromConfig(Config $config): self
    {
        $definitions = [];
        $tiers = $config->rollupTiers();

        if ($tiers !== []) {
            $definitions[] = new RollupDefinition(
                self::BuiltIn,
                self::ordered($tiers),
                array_map(Grouping::from(...), $config->rollupGroupings()),
            );
        }

        foreach (self::dimensions($config, $tiers) as $definition) {
            $definitions[] = $definition;
        }

        foreach ($config->customRollups() as $class) {
            $definition = self::define($class);

            foreach ($definitions as $existing) {
                if ($existing->name === $definition->name) {
                    throw InvalidConfiguration::invalidRollup($class, "is named `{$definition->name}`, which another rollup is named already");
                }
            }

            $definitions[] = $definition;
        }

        return new self(
            $definitions,
            self::timezone($config),
            $config->rollupSettle(),
            $config->rollupsStrict(),
            $config->rollupTable(),
            $config->pruneAfter(),
        );
    }

    public function isEnabled(): bool
    {
        return $this->definitions !== [];
    }

    /** @return list<RollupDefinition> */
    public function definitions(): array
    {
        return $this->definitions;
    }

    public function find(string $name): ?RollupDefinition
    {
        foreach ($this->definitions as $definition) {
            if ($definition->name === $name) {
                return $definition;
            }
        }

        return null;
    }

    /**
     * A run folds a tier up to here: the start of the bucket that has not
     * settled yet.
     */
    public function closedUntil(Tier $tier, CarbonInterface $now): CarbonImmutable
    {
        return $tier->floor($this->settle?->before($now) ?? $now, $this->timezone);
    }

    /**
     * The rollup that folds the dimension, or null when it is not folded.
     */
    public function forDimension(string $name): ?RollupDefinition
    {
        return $this->find(self::DimensionPrefix.$name);
    }

    public function for(ViewsQuery $query): ?RollupDefinition
    {
        if (! $query->filter instanceof FiltersViews) {
            return $this->find(self::BuiltIn);
        }

        return $this->find($query->filter->name());
    }

    private function coarsestTier(): ?Tier
    {
        $coarsest = null;

        foreach ($this->definitions as $definition) {
            foreach ($definition->tiers() as $tier) {
                if ($coarsest instanceof Tier && ! $tier->isCoarserThan($coarsest)) {
                    continue;
                }

                $coarsest = $tier;
            }
        }

        return $coarsest;
    }

    /** @throws InvalidTimezone */
    private static function timezone(Config $config): Timezone
    {
        $timezone = $config->rollupTimezone();

        if ($timezone === null) {
            return Timezone::application();
        }

        return new Timezone($timezone);
    }

    /**
     * A definition per dimension in `retention.rollups.dimensions`, with the
     * tiers and groupings of the built-in rollup. The dimensions are only
     * built when one is folded, so a policy read at boot costs nothing more
     * without them.
     *
     * @param  array<'hour'|'day'|'month'|'year', ?Duration>  $tiers
     * @return list<RollupDefinition>
     *
     * @throws InvalidConfiguration
     */
    private static function dimensions(Config $config, array $tiers): array
    {
        $names = $config->rollupDimensions();

        if ($names === []) {
            return [];
        }

        if ($tiers === []) {
            throw InvalidConfiguration::dimensionsWithoutTiers();
        }

        $registry = DimensionRegistry::fromConfig($config, Container::getInstance());
        $groupings = array_map(Grouping::from(...), $config->rollupGroupings());
        $definitions = [];

        foreach (array_unique($names) as $name) {
            $dimension = $registry->find($name) ?? throw InvalidConfiguration::unknownDimension('retention.rollups.dimensions', $name);

            $definitions[] = new RollupDefinition(
                self::DimensionPrefix.$name,
                self::ordered($tiers),
                $groupings,
                dimension: $dimension->target(),
                maxValues: $dimension->maxValues(),
            );
        }

        return $definitions;
    }

    /**
     * @param  class-string  $class
     *
     * @throws InvalidConfiguration
     */
    private static function define(string $class): RollupDefinition
    {
        $rollup = Container::getInstance()->make($class);

        if (! $rollup instanceof Rollup) {
            $parent = Rollup::class;

            throw InvalidConfiguration::invalidRollup($class, "must extend `{$parent}`");
        }

        return new RollupDefinition(
            self::nameOf($class, $rollup),
            self::ordered(self::tiersOf($class, $rollup)),
            self::groupingsOf($class, $rollup),
            $rollup,
        );
    }

    /** @throws InvalidConfiguration */
    private static function nameOf(string $class, Rollup $rollup): string
    {
        $builtIn = self::BuiltIn;
        $problem = "must have a `name` of letters, digits, `-` and `_` other than `{$builtIn}`";

        if (preg_match('/^[A-Za-z0-9_-]+$/', $rollup->name) !== 1) {
            throw InvalidConfiguration::invalidRollup($class, $problem);
        }

        if ($rollup->name === $builtIn) {
            throw InvalidConfiguration::invalidRollup($class, $problem);
        }

        return $rollup->name;
    }

    /**
     * @return array<string, ?Duration>
     *
     * @throws InvalidConfiguration
     */
    private static function tiersOf(string $class, Rollup $rollup): array
    {
        $problem = 'must map `hour`, `day`, `month` or `year` to a duration such as `2y`, or null, in `tiers()`';
        $tiers = [];

        foreach ($rollup->tiers() as $tier => $keep) {
            if (! in_array($tier, self::Tiers, true)) {
                throw InvalidConfiguration::invalidRollup($class, $problem);
            }

            if ($keep === null) {
                $tiers[$tier] = null;

                continue;
            }

            $tiers[$tier] = Duration::tryParse($keep) ?? throw InvalidConfiguration::invalidRollup($class, $problem);
        }

        if ($tiers === []) {
            throw InvalidConfiguration::invalidRollup($class, self::missingGroupingOrTier());
        }

        return $tiers;
    }

    /**
     * @return list<Grouping>
     *
     * @throws InvalidConfiguration
     */
    private static function groupingsOf(string $class, Rollup $rollup): array
    {
        $groupings = $rollup->groupings();

        if ($groupings === []) {
            throw InvalidConfiguration::invalidRollup($class, self::missingGroupingOrTier());
        }

        if (array_diff($groupings, self::Groupings) !== []) {
            throw InvalidConfiguration::invalidRollup($class, self::missingGroupingOrTier());
        }

        return array_map(Grouping::from(...), array_values(array_intersect(self::Groupings, $groupings)));
    }

    private static function missingGroupingOrTier(): string
    {
        $groupings = implode('`, `', self::Groupings);

        return "must keep at least one tier, and at least one grouping of `{$groupings}`";
    }

    /**
     * @param  array<string, ?Duration>  $tiers
     * @return array<string, ?Duration> coarse to fine
     */
    private static function ordered(array $tiers): array
    {
        $ordered = [];

        foreach (Tier::coarseToFine() as $tier) {
            if (array_key_exists($tier->value, $tiers)) {
                $ordered[$tier->value] = $tiers[$tier->value];
            }
        }

        return $ordered;
    }

    private function keptShorter(RollupDefinition $definition, Tier $coarser, Tier $finer, Carbon $now): bool
    {
        $coarserKeep = $definition->keep($coarser);
        $finerKeep = $definition->keep($finer);

        if (! $coarserKeep instanceof Duration) {
            return false;
        }

        if (! $finerKeep instanceof Duration) {
            return true;
        }

        return $finerKeep->isLongerThan($coarserKeep, $now);
    }
}
