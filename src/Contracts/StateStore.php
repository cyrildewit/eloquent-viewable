<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Contracts;

/**
 * Implement this to keep the marks that outlive a run: how far each rollup tier
 * is folded, how far the views table is anonymised and pruned, and what the
 * milestones and recounts last saw.
 *
 * An implementation may keep values in memory, as long as a read returns
 * every write made through the same instance.
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
     * Read the values of many names in one round trip. A name without a value
     * is left out of the result.
     *
     * @param  list<string>  $names
     * @return array<string, string>
     */
    public function many(array $names): array;

    public function get(string $name): ?string;

    public function put(string $name, string $value): void;

    public function forget(string $name): void;
}
