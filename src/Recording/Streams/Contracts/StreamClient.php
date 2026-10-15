<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Recording\Streams\Contracts;

use CyrildeWit\EloquentViewable\Recording\Streams\StreamEntry;

/** @internal */
interface StreamClient
{
    /** @param  array<string, string>  $fields */
    public function add(string $stream, array $fields): void;

    /** @param  list<array<string, string>>  $batch */
    public function addMany(string $stream, array $batch): void;

    public function createGroup(string $stream, string $group): void;

    /** @return list<StreamEntry> */
    public function claim(string $stream, string $group, string $consumer, int $idle, int $count): array;

    /** @return list<StreamEntry> */
    public function read(string $stream, string $group, string $consumer, int $count): array;

    /** @return list<StreamEntry> */
    public function range(string $stream, ?string $after, int $count): array;

    /** @param  list<string>  $ids */
    public function acknowledge(string $stream, string $group, array $ids): void;

    /** @param  list<string>  $ids */
    public function delete(string $stream, array $ids): void;

    public function length(string $stream): int;

    /**
     * How many entries the group has delivered without an acknowledgement,
     * or 0 when the group does not exist yet.
     */
    public function pending(string $stream, string $group): int;

    /**
     * How many of the pending entries, up to `$count`, have been idle for at
     * least `$idle` milliseconds.
     */
    public function stalled(string $stream, string $group, int $idle, int $count): int;
}
