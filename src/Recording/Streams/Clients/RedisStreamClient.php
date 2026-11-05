<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Recording\Streams\Clients;

use CyrildeWit\EloquentViewable\Recording\Streams\Contracts\StreamClient;
use CyrildeWit\EloquentViewable\Recording\Streams\StreamEntry;
use Illuminate\Redis\Connections\Connection;

/**
 * What the phpredis and Predis clients share: the commands whose reply the
 * two libraries shape alike. Each client keeps the ones they shape
 * differently, and the way an error reply reaches it.
 *
 * @internal
 */
abstract readonly class RedisStreamClient implements StreamClient
{
    /** @return list<StreamEntry> */
    public function range(string $stream, ?string $after, int $count): array
    {
        $start = $after === null ? '-' : "({$after}";

        return $this->entries($this->connection()->command('xrange', [$stream, $start, '+', $count]));
    }

    public function length(string $stream): int
    {
        $length = $this->connection()->command('xlen', [$stream]);

        return is_int($length) ? $length : 0;
    }

    public function pending(string $stream, string $group): int
    {
        $summary = $this->pendingSummary($stream, $group);
        $count = is_array($summary) ? $summary[0] ?? 0 : 0;

        return is_numeric($count) ? (int) $count : 0;
    }

    abstract protected function connection(): Connection;

    /**
     * The `XPENDING` summary of the group, or null for a group that does
     * not exist yet, which has nothing pending.
     */
    abstract protected function pendingSummary(string $stream, string $group): mixed;

    /**
     * @param  mixed  $map  `[id => [field => value]]`, or `false` for an error reply
     * @return list<StreamEntry>
     */
    protected function entries(mixed $map): array
    {
        if (! is_array($map)) {
            return [];
        }

        $entries = [];

        foreach ($map as $id => $fields) {
            $entries[] = StreamEntry::of((string) $id, $fields);
        }

        return $entries;
    }
}
