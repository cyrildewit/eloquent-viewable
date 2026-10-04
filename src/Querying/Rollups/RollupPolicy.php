<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Querying\Rollups;

use CyrildeWit\EloquentViewable\Exceptions\InvalidConfiguration;
use CyrildeWit\EloquentViewable\Exceptions\InvalidTimezone;
use CyrildeWit\EloquentViewable\Support\Config;
use CyrildeWit\EloquentViewable\Support\Duration;
use CyrildeWit\EloquentViewable\Support\Timezone;
use Illuminate\Support\Carbon;

/**
 * The `retention.rollups` config, read and checked once.
 */
final readonly class RollupPolicy
{
    /**
     * @param  array<string, Duration|null>  $tiers  keyed by tier, coarse to fine
     * @param  list<Grouping>  $groupings
     *
     * @throws InvalidConfiguration
     */
    public function __construct(
        private array $tiers,
        public array $groupings,
        public Timezone $timezone,
        public ?Duration $settle,
        public bool $strict,
        public string $table,
        ?Duration $pruneAfter = null,
    ) {
        $now = Carbon::now();
        $finer = null;

        foreach (array_reverse($this->tiers()) as $tier) {
            if ($finer instanceof Tier && $this->keptShorter($tier, $finer, $now)) {
                throw InvalidConfiguration::coarserTierKeptShorter($tier->value, $finer->value);
            }

            $finer = $tier;
        }

        $coarsest = $this->tiers()[0] ?? null;

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
        $configured = $config->rollupTiers();
        $tiers = [];

        foreach (Tier::coarseToFine() as $tier) {
            if (array_key_exists($tier->value, $configured)) {
                $tiers[$tier->value] = $configured[$tier->value];
            }
        }

        $timezone = $config->rollupTimezone();

        return new self(
            $tiers,
            array_map(Grouping::from(...), $config->rollupGroupings()),
            $timezone === null ? Timezone::application() : new Timezone($timezone),
            $config->rollupSettle(),
            $config->rollupsStrict(),
            $config->rollupTable(),
            $config->pruneAfter(),
        );
    }

    public function isEnabled(): bool
    {
        return $this->tiers !== [];
    }

    /**
     * The configured tiers, coarse to fine.
     *
     * @return list<Tier>
     */
    public function tiers(): array
    {
        return array_map(Tier::from(...), array_keys($this->tiers));
    }

    /**
     * Null when the tier is kept forever.
     */
    public function keep(Tier $tier): ?Duration
    {
        return $this->tiers[$tier->value] ?? null;
    }

    /**
     * The next configured tier coarser than the given one.
     */
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

    private function keptShorter(Tier $coarser, Tier $finer, Carbon $now): bool
    {
        $coarserKeep = $this->keep($coarser);
        $finerKeep = $this->keep($finer);

        if (! $coarserKeep instanceof Duration) {
            return false;
        }

        return ! $finerKeep instanceof Duration || $finerKeep->isLongerThan($coarserKeep, $now);
    }
}
