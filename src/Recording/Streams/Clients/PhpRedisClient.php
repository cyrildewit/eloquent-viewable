<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Recording\Streams\Clients;

use CyrildeWit\EloquentViewable\Recording\Exceptions\RedisStreamFailed;
use CyrildeWit\EloquentViewable\Recording\Streams\Contracts\StreamClient;
use CyrildeWit\EloquentViewable\Recording\Streams\StreamEntry;
use Illuminate\Redis\Connections\PhpRedisConnection;
use Redis;

/** @internal */
final readonly class PhpRedisClient implements StreamClient
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

        $error = $this->lastError();

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

    /** @return list<StreamEntry> */
    public function range(string $stream, ?string $after, int $count): array
    {
        $start = $after === null ? '-' : "({$after}";

        return $this->entries($this->connection->command('xrange', [$stream, $start, '+', $count]));
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

    private function lastError(): string
    {
        $client = $this->connection->client();

        if (! $client instanceof Redis) {
            return '';
        }

        $error = (string) $client->getLastError();

        $client->clearLastError();

        return $error;
    }

    /**
     * @param  mixed  $map  `[id => [field => value]]`, or `false` for an error reply
     * @return list<StreamEntry>
     */
    private function entries(mixed $map): array
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
