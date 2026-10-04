<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Querying\Rollups\Contracts;

/**
 * Implement this to keep the names and values that outlive a run: how far
 * each rollup tier is folded and how far the views table is anonymised and
 * pruned. One table holds both, so the source knows where the views end and
 * the rollups begin.
 */
interface StateStore
{
    /** The views before this moment are anonymised. */
    public const string Anonymised = 'anonymised';

    /** The views before this moment are deleted. */
    public const string Pruned = 'pruned';

    /** Moments are kept in this format, on the clock of `viewed_at`. */
    public const string Format = 'Y-m-d H:i:s';

    public function installed(): bool;

    /**
     * It reads the values of the names that are set in one round trip.
     *
     * @param  list<string>  $names
     * @return array<string, string>
     */
    public function many(array $names): array;

    public function get(string $name): ?string;

    public function put(string $name, string $value): void;

    public function forget(string $name): void;
}
