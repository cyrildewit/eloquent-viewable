<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Recording\Streams\Clients;

use CyrildeWit\EloquentViewable\Recording\Streams\Contracts\StreamClient;
use CyrildeWit\EloquentViewable\Recording\Streams\StreamEntry;
use Illuminate\Redis\Connections\PredisConnection;
use Predis\Pipeline\Pipeline;
use Predis\Response\ServerException;

/** @internal */
final readonly class PredisClient implements StreamClient
{
    public function __construct(private PredisConnection $connection) {}

    /** @param  array<string, string>  $fields */
    public function add(string $stream, array $fields): void
    {
        $this->connection->command('xadd', [$stream, $fields, '*']);
    }

    /** @param  list<array<string, string>>  $batch */
    public function addMany(string $stream, array $batch): void
    {
        $this->connection->pipeline(function (Pipeline $pipe) use ($stream, $batch): void {
            foreach ($batch as $fields) {
                $pipe->xadd($stream, $fields, '*');
            }
        });
    }

    public function createGroup(string $stream, string $group): void
    {
        try {
            $this->connection->command('xgroup', ['CREATE', $stream, $group, '0', true]);
        } catch (ServerException $exception) {
            if (! str_contains($exception->getMessage(), 'BUSYGROUP')) {
                throw $exception;
            }
        }
    }

    /**
     * Predis does not apply the connection's key prefix to XAUTOCLAIM, so
     * the pending entries are listed with XPENDING and taken over with
     * XCLAIM instead.
     *
     * @return list<StreamEntry>
     */
    public function claim(string $stream, string $group, string $consumer, int $idle, int $count): array
    {
        $pending = $this->connection->command('xpending', [$stream, $group, $idle, '-', '+', $count]);

        $ids = [];

        foreach (is_array($pending) ? $pending : [] as $row) {
            $id = is_array($row) ? $row[0] ?? null : null;

            if (is_string($id)) {
                $ids[] = $id;
            }
        }

        if ($ids === []) {
            return [];
        }

        return $this->fromMap($this->connection->command('xclaim', [$stream, $group, $consumer, $idle, $ids]));
    }

    /** @return list<StreamEntry> */
    public function read(string $stream, string $group, string $consumer, int $count): array
    {
        $response = $this->connection->command('xreadgroup', [$group, $consumer, $count, null, false, $stream, '>']);

        if (! is_array($response)) {
            return [];
        }

        $first = $response[0] ?? null;

        if (! is_array($first)) {
            return [];
        }

        return $this->fromPairs($first[1] ?? []);
    }

    /** @return list<StreamEntry> */
    public function range(string $stream, ?string $after, int $count): array
    {
        $start = $after === null ? '-' : "({$after}";

        return $this->fromMap($this->connection->command('xrange', [$stream, $start, '+', $count]));
    }

    /** @param  list<string>  $ids */
    public function acknowledge(string $stream, string $group, array $ids): void
    {
        $this->connection->command('xack', [$stream, $group, ...$ids]);
    }

    /** @param  list<string>  $ids */
    public function delete(string $stream, array $ids): void
    {
        $this->connection->command('xdel', [$stream, ...$ids]);
    }

    /**
     * @param  mixed  $map  `[id => [field => value]]`
     * @return list<StreamEntry>
     */
    private function fromMap(mixed $map): array
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

    /**
     * @param  mixed  $pairs  `[[id, [field, value, ...]], ...]`, with `null` fields for a deleted entry
     * @return list<StreamEntry>
     */
    private function fromPairs(mixed $pairs): array
    {
        if (! is_array($pairs)) {
            return [];
        }

        $entries = [];

        foreach ($pairs as $pair) {
            $id = is_array($pair) ? $pair[0] ?? null : null;

            if (is_string($id)) {
                $entries[] = StreamEntry::of($id, $this->fields($pair[1] ?? null));
            }
        }

        return $entries;
    }

    /**
     * @param  mixed  $flat  `[field, value, field, value, ...]`
     * @return array<string, string>
     */
    private function fields(mixed $flat): array
    {
        if (! is_array($flat)) {
            return [];
        }

        $fields = [];

        foreach (array_chunk(array_values($flat), 2) as $pair) {
            $name = $pair[0];
            $value = $pair[1] ?? null;

            if (is_string($name) && is_scalar($value)) {
                $fields[$name] = (string) $value;
            }
        }

        return $fields;
    }
}
