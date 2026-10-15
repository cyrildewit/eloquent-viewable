<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Querying\Rollups;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use CyrildeWit\EloquentViewable\Querying\Rollups\Contracts\StateStore;

/** @internal */
final readonly class RollupState
{
    private const string LastId = 'rollup:last_id';

    public function __construct(
        private StateStore $store,
    ) {}

    public function installed(): bool
    {
        return $this->store->installed();
    }

    public function snapshot(string $rollup): Snapshot
    {
        $names = [StateStore::Anonymised, StateStore::Pruned, $this->origin($rollup)];

        foreach (Tier::cases() as $tier) {
            $names[] = $this->folded($rollup, $tier);
            $names[] = $this->since($rollup, $tier);
        }

        $values = $this->store->many($names);
        $moment = static function (string $name) use ($values): ?CarbonImmutable {
            if (! isset($values[$name])) {
                return null;
            }

            return CarbonImmutable::parse($values[$name]);
        };
        $folded = [];
        $since = [];

        foreach (Tier::cases() as $tier) {
            $folded[$tier->value] = $moment($this->folded($rollup, $tier));
            $since[$tier->value] = $moment($this->since($rollup, $tier));
        }

        return new Snapshot(
            array_filter($folded),
            array_filter($since),
            $moment(StateStore::Anonymised),
            $moment(StateStore::Pruned),
            $moment($this->origin($rollup)),
        );
    }

    public function putFolded(string $rollup, Tier $tier, CarbonInterface $until): void
    {
        $this->store->put($this->folded($rollup, $tier), $until->format(StateStore::Format));
    }

    public function putSince(string $rollup, Tier $tier, CarbonInterface $since): void
    {
        $this->store->put($this->since($rollup, $tier), $since->format(StateStore::Format));
    }

    public function putOrigin(string $rollup, CarbonInterface $origin): void
    {
        $this->store->put($this->origin($rollup), $origin->format(StateStore::Format));
    }

    /**
     * The last id is shared by every rollup: a run only moves it once all of
     * them have looked at the views below it.
     */
    public function lastId(): ?int
    {
        $value = $this->store->get(self::LastId);

        if ($value === null) {
            return null;
        }

        return (int) $value;
    }

    public function putLastId(int $id): void
    {
        $this->store->put(self::LastId, (string) $id);
    }

    private function folded(string $rollup, Tier $tier): string
    {
        return "rollup:{$rollup}:{$tier->value}";
    }

    private function since(string $rollup, Tier $tier): string
    {
        return $this->folded($rollup, $tier).':since';
    }

    private function origin(string $rollup): string
    {
        return "rollup:{$rollup}:origin";
    }
}
