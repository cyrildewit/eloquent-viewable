<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Querying\Rollups;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use CyrildeWit\EloquentViewable\Querying\Rollups\Contracts\StateStore;

/**
 * The marks of each rollup in the state store, kept under its name.
 *
 * @internal
 */
final readonly class RollupState
{
    private const string LAST_ID = 'rollup:last_id';

    public function __construct(
        private StateStore $store,
    ) {}

    public function installed(): bool
    {
        return $this->store->installed();
    }

    /**
     * Every mark a read of the rollup needs, in one round trip.
     */
    public function snapshot(string $rollup): Snapshot
    {
        $names = [StateStore::ANONYMISED, StateStore::PRUNED, $this->origin($rollup)];

        foreach (Tier::cases() as $tier) {
            $names[] = self::folded($rollup, $tier);
            $names[] = $this->since($rollup, $tier);
        }

        $values = $this->store->many($names);
        $moment = static fn (string $name): ?CarbonImmutable => isset($values[$name]) ? CarbonImmutable::parse($values[$name]) : null;
        $folded = [];
        $since = [];

        foreach (Tier::cases() as $tier) {
            $folded[$tier->value] = $moment(self::folded($rollup, $tier));
            $since[$tier->value] = $moment($this->since($rollup, $tier));
        }

        return new Snapshot(
            array_filter($folded),
            array_filter($since),
            $moment(StateStore::ANONYMISED),
            $moment(StateStore::PRUNED),
            $moment($this->origin($rollup)),
        );
    }

    public function putFolded(string $rollup, Tier $tier, CarbonInterface $until): void
    {
        $this->store->put(self::folded($rollup, $tier), $until->format(StateStore::FORMAT));
    }

    public function putSince(string $rollup, Tier $tier, CarbonInterface $since): void
    {
        $this->store->put($this->since($rollup, $tier), $since->format(StateStore::FORMAT));
    }

    public function putOrigin(string $rollup, CarbonInterface $origin): void
    {
        $this->store->put($this->origin($rollup), $origin->format(StateStore::FORMAT));
    }

    /**
     * Shared by every rollup: a run only moves it once all of them have
     * looked at the views below it.
     */
    public function lastId(): ?int
    {
        $value = $this->store->get(self::LAST_ID);

        return $value === null ? null : (int) $value;
    }

    public function putLastId(int $id): void
    {
        $this->store->put(self::LAST_ID, (string) $id);
    }

    private static function folded(string $rollup, Tier $tier): string
    {
        return "rollup:{$rollup}:{$tier->value}";
    }

    private function since(string $rollup, Tier $tier): string
    {
        return self::folded($rollup, $tier).':since';
    }

    private function origin(string $rollup): string
    {
        return "rollup:{$rollup}:origin";
    }
}
