<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Querying\Rollups;

use Carbon\CarbonImmutable;

/** @internal */
final readonly class Snapshot
{
    /**
     * @param  array<string, CarbonImmutable>  $folded  the end of the last folded bucket, by tier
     * @param  array<string, CarbonImmutable>  $since  where the rows of a tier start, by tier
     */
    public function __construct(
        private array $folded = [],
        private array $since = [],
        public ?CarbonImmutable $anonymised = null,
        public ?CarbonImmutable $pruned = null,
        public ?CarbonImmutable $origin = null,
    ) {}

    public function folded(Tier $tier): ?CarbonImmutable
    {
        return $this->folded[$tier->value] ?? null;
    }

    public function since(Tier $tier): ?CarbonImmutable
    {
        return $this->since[$tier->value] ?? null;
    }
}
