<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Querying\Rollups;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use CyrildeWit\EloquentViewable\Querying\Rollups\Contracts\StateStore;

/**
 * The marks of the built-in rollup in the state store.
 *
 * @internal
 */
final readonly class RollupState
{
    public const string ROLLUP = 'views';

    private const string LAST_ID = 'rollup:last_id';

    private const string ORIGIN = 'rollup:'.self::ROLLUP.':origin';

    public function __construct(
        private StateStore $store,
    ) {}

    public function installed(): bool
    {
        return $this->store->installed();
    }

    /**
     * Every mark a read needs, in one round trip.
     */
    public function snapshot(): Snapshot
    {
        $names = [StateStore::ANONYMISED, StateStore::PRUNED, self::ORIGIN];

        foreach (Tier::cases() as $tier) {
            $names[] = $this->folded($tier);
            $names[] = $this->since($tier);
        }

        $values = $this->store->many($names);
        $folded = [];
        $since = [];

        foreach (Tier::cases() as $tier) {
            if (isset($values[$this->folded($tier)])) {
                $folded[$tier->value] = $this->parse($values[$this->folded($tier)]);
            }

            if (isset($values[$this->since($tier)])) {
                $since[$tier->value] = $this->parse($values[$this->since($tier)]);
            }
        }

        return new Snapshot(
            $folded,
            $since,
            isset($values[StateStore::ANONYMISED]) ? $this->parse($values[StateStore::ANONYMISED]) : null,
            isset($values[StateStore::PRUNED]) ? $this->parse($values[StateStore::PRUNED]) : null,
            isset($values[self::ORIGIN]) ? $this->parse($values[self::ORIGIN]) : null,
        );
    }

    public function putFolded(Tier $tier, CarbonInterface $until): void
    {
        $this->store->put($this->folded($tier), $until->format(StateStore::FORMAT));
    }

    public function putSince(Tier $tier, CarbonInterface $since): void
    {
        $this->store->put($this->since($tier), $since->format(StateStore::FORMAT));
    }

    public function putOrigin(CarbonInterface $origin): void
    {
        $this->store->put(self::ORIGIN, $origin->format(StateStore::FORMAT));
    }

    public function lastId(): ?int
    {
        $value = $this->store->get(self::LAST_ID);

        return $value === null ? null : (int) $value;
    }

    public function putLastId(int $id): void
    {
        $this->store->put(self::LAST_ID, (string) $id);
    }

    private function folded(Tier $tier): string
    {
        return 'rollup:'.self::ROLLUP.":{$tier->value}";
    }

    private function since(Tier $tier): string
    {
        return $this->folded($tier).':since';
    }

    private function parse(string $value): CarbonImmutable
    {
        return CarbonImmutable::parse($value);
    }
}
