<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Tests\Fixtures\Streams;

use CyrildeWit\EloquentViewable\Recording\Streams\Contracts\StreamClient;
use CyrildeWit\EloquentViewable\Recording\Streams\StreamEntry;

final class FakeStreamClient implements StreamClient
{
    /** @var array<string, array<string, string>> */
    public array $entries = [];

    /** @var list<string> */
    public array $groups = [];

    /** @var list<string> */
    public array $delivered = [];

    /** @var list<string> */
    public array $acknowledged = [];

    /** @var list<string> */
    public array $abandoned = [];

    /** @var list<list<array<string, string>>> */
    public array $batches = [];

    private int $sequence = 0;

    /** @param  array<string, string>  $fields */
    public function add(string $stream, array $fields): void
    {
        $this->entries[sprintf('%d-0', ++$this->sequence)] = $fields;
    }

    /** @param  list<array<string, string>>  $batch */
    public function addMany(string $stream, array $batch): void
    {
        $this->batches[] = $batch;

        foreach ($batch as $fields) {
            $this->add($stream, $fields);
        }
    }

    public function createGroup(string $stream, string $group): void
    {
        $this->groups[] = $group;
    }

    /** @return list<StreamEntry> */
    public function claim(string $stream, string $group, string $consumer, int $idle, int $count): array
    {
        $ids = array_splice($this->abandoned, 0, $count);

        return array_map(fn (string $id): StreamEntry => new StreamEntry($id, $this->entries[$id] ?? []), $ids);
    }

    /** @return list<StreamEntry> */
    public function read(string $stream, string $group, string $consumer, int $count): array
    {
        $entries = [];

        foreach ($this->entries as $id => $fields) {
            if (count($entries) === $count) {
                break;
            }

            if (! in_array($id, $this->delivered, true)) {
                $this->delivered[] = $id;
                $entries[] = new StreamEntry($id, $fields);
            }
        }

        return $entries;
    }

    /** @return list<StreamEntry> */
    public function range(string $stream, ?string $after, int $count): array
    {
        $entries = [];

        foreach ($this->entries as $id => $fields) {
            if (count($entries) === $count) {
                break;
            }

            if ($after === null || strnatcmp($id, $after) > 0) {
                $entries[] = new StreamEntry($id, $fields);
            }
        }

        return $entries;
    }

    /** @param  list<string>  $ids */
    public function acknowledge(string $stream, string $group, array $ids): void
    {
        $this->acknowledged = [...$this->acknowledged, ...$ids];
    }

    /** @param  list<string>  $ids */
    public function delete(string $stream, array $ids): void
    {
        foreach ($ids as $id) {
            unset($this->entries[$id]);
        }
    }

    public function length(string $stream): int
    {
        return count($this->entries);
    }

    public function pending(string $stream, string $group): int
    {
        return count(array_diff($this->delivered, $this->acknowledged));
    }

    public function stalled(string $stream, string $group, int $idle, int $count): int
    {
        return min(count($this->abandoned), $count);
    }

    /** @return list<string> */
    public function ids(): array
    {
        return array_keys($this->entries);
    }
}
