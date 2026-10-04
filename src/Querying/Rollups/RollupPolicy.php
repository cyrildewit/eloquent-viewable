<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Querying\Rollups;

use CyrildeWit\EloquentViewable\Contracts\FiltersViews;
use CyrildeWit\EloquentViewable\Exceptions\InvalidConfiguration;
use CyrildeWit\EloquentViewable\Exceptions\InvalidTimezone;
use CyrildeWit\EloquentViewable\Support\Config;
use CyrildeWit\EloquentViewable\Support\Duration;
use CyrildeWit\EloquentViewable\Support\Timezone;
use CyrildeWit\EloquentViewable\Support\ViewsQuery;
use Illuminate\Container\Container;
use Illuminate\Support\Carbon;

/**
 * The `retention.rollups` config, read and checked once: the built-in rollup
 * when tiers are configured, and every custom rollup.
 */
final readonly class RollupPolicy
{
    public const string BUILT_IN = 'views';

    private const array TIERS = ['hour', 'day', 'month', 'year'];

    private const array GROUPINGS = ['viewable', 'viewable_collection', 'type', 'type_collection'];

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
                self::BUILT_IN,
                self::ordered($tiers),
                array_map(Grouping::from(...), $config->rollupGroupings()),
            );
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

        $timezone = $config->rollupTimezone();

        return new self(
            $definitions,
            $timezone === null ? Timezone::application() : new Timezone($timezone),
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
     * The rollup a read goes through: the custom one its filter names, or the
     * built-in one without a filter.
     */
    public function for(ViewsQuery $query): ?RollupDefinition
    {
        return $this->find($query->filter instanceof FiltersViews ? $query->filter->name() : self::BUILT_IN);
    }

    private function coarsestTier(): ?Tier
    {
        $coarsest = null;

        foreach ($this->definitions as $definition) {
            $tier = $definition->tiers()[0] ?? null;

            if ($tier instanceof Tier && (! $coarsest instanceof Tier || $tier->isCoarserThan($coarsest))) {
                $coarsest = $tier;
            }
        }

        return $coarsest;
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
            throw InvalidConfiguration::invalidRollup($class, 'must extend `'.Rollup::class.'`');
        }

        $name = $rollup->name;

        if (preg_match('/^[A-Za-z0-9_-]+$/', $name) !== 1 || $name === self::BUILT_IN) {
            throw InvalidConfiguration::invalidRollup($class, 'must have a `name` of letters, digits, `-` and `_` other than `'.self::BUILT_IN.'`');
        }

        $tiers = [];

        foreach ($rollup->tiers() as $tier => $keep) {
            $duration = is_string($keep) ? Duration::tryParse($keep) : null;

            if (! in_array($tier, self::TIERS, true) || ($keep !== null && ! $duration instanceof Duration)) {
                throw InvalidConfiguration::invalidRollup($class, 'must map `hour`, `day`, `month` or `year` to a duration such as `2y`, or null, in `tiers()`');
            }

            $tiers[$tier] = $duration;
        }

        $groupings = $rollup->groupings();

        if ($tiers === [] || $groupings === [] || array_diff($groupings, self::GROUPINGS) !== []) {
            throw InvalidConfiguration::invalidRollup($class, 'must keep at least one tier, and at least one grouping of `'.implode('`, `', self::GROUPINGS).'`');
        }

        return new RollupDefinition(
            $name,
            self::ordered($tiers),
            array_map(Grouping::from(...), array_values(array_intersect(self::GROUPINGS, $groupings))),
            $rollup,
        );
    }

    /**
     * @param  array<string, Duration|null>  $tiers
     * @return array<string, Duration|null> coarse to fine
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

        return ! $finerKeep instanceof Duration || $finerKeep->isLongerThan($coarserKeep, $now);
    }
}
