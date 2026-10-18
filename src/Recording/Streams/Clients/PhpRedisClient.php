<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Recording\Streams\Clients;

use CyrildeWit\EloquentViewable\Recording\Exceptions\RedisStreamFailed;
use CyrildeWit\EloquentViewable\Recording\Streams\StreamEntry;
use CyrildeWit\EloquentViewable\Support\RedisError;
use Illuminate\Redis\Connections\PhpRedisConnection;
use Redis;

/** @internal */
final readonly class PhpRedisClient extends RedisStreamClient
{
    public function __construct(private PhpRedisConnection $connection) {}

    /** @param  array<string, string>  $fields */
    public function add(string $stream, array $fields): void
    {
        $this->connection->command('xadd', [$stream, '*', $fields]);
    }

    /** @param  list<array<string, string>>  $batch */
    public function addMany(string $stream, array $batch): void
    {
        $this->connection->pipeline(function (Redis $pipe) use ($stream, $batch): void {
            foreach ($batch as $fields) {
                $pipe->xadd($stream, '*', $fields);
            }
        });
    }

    /** @throws RedisStreamFailed */
    public function createGroup(string $stream, string $group): void
    {
        if ($this->connection->command('xgroup', ['CREATE', $stream, $group, '0', true]) !== false) {
            return;
        }

        $error = RedisError::last($this->connection);

        if (! str_contains($error, 'BUSYGROUP')) {
            throw RedisStreamFailed::creatingGroup($stream, $group, $error);
        }
    }

    /** @return list<StreamEntry> */
    public function claim(string $stream, string $group, string $consumer, int $idle, int $count): array
    {
        $response = $this->connection->command('xautoclaim', [$stream, $group, $consumer, $idle, '0-0', $count]);

        return $this->entries(is_array($response) ? $response[1] ?? [] : []);
    }

    /**
     * The reply is keyed by the stream name including the connection's key
     * prefix, so the single stream is taken by position.
     *
     * @return list<StreamEntry>
     */
    public function read(string $stream, string $group, string $consumer, int $count): array
    {
        $response = $this->connection->command('xreadgroup', [$group, $consumer, [$stream => '>'], $count]);

        if (! is_array($response)) {
            return [];
        }

        return $this->entries(array_first($response) ?? []);
    }

    /** @param  list<string>  $ids */
    public function acknowledge(string $stream, string $group, array $ids): void
    {
        $this->connection->command('xack', [$stream, $group, $ids]);
    }

    /** @param  list<string>  $ids */
    public function delete(string $stream, array $ids): void
    {
        $this->connection->command('xdel', [$stream, $ids]);
    }

    /**
     * The entries are filtered by their idle time here, because phpredis
     * leaves the connection unusable after an `XPENDING` with `IDLE`.
     */
    public function stalled(string $stream, string $group, int $idle, int $count): int
    {
        $pending = $this->connection->command('xpending', [$stream, $group, '-', '+', $count]);

        if (! is_array($pending)) {
            RedisError::last($this->connection);

            return 0;
        }

        $stalled = 0;

        foreach ($pending as $row) {
            $idleFor = is_array($row) ? $row[2] ?? 0 : 0;

            if (is_int($idleFor) && $idleFor >= $idle) {
                $stalled++;
            }
        }

        return $stalled;
    }

    protected function connection(): PhpRedisConnection
    {
        return $this->connection;
    }

    /**
     * An unknown group is an error reply, which phpredis returns as `false`
     * and keeps as the last error until it is cleared.
     */
    protected function pendingSummary(string $stream, string $group): mixed
    {
        $summary = $this->connection->command('xpending', [$stream, $group]);

        if (! is_array($summary)) {
            RedisError::last($this->connection);

            return null;
        }

        return $summary;
    }
}
