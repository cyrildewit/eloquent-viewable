<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Querying\Rollups\Contracts;

/**
 * Names and values that outlive a run: how far each rollup tier is folded and
 * how far the views table is anonymised and pruned. One table holds both, so
 * the source knows where the views end and the rollups begin.
 */
interface StateStore
{
    /** Views before this moment are anonymised. */
    public const string ANONYMISED = 'anonymised';

    /** Views before this moment are deleted. */
    public const string PRUNED = 'pruned';

    /** The format moments are kept in, on the clock of `viewed_at`. */
    public const string FORMAT = 'Y-m-d H:i:s';

    public function installed(): bool;

    /**
     * The values of the names that are set, in one read.
     *
     * @param  list<string>  $names
     * @return array<string, string>
     */
    public function many(array $names): array;

    public function get(string $name): ?string;

    public function put(string $name, string $value): void;

    public function forget(string $name): void;
}
