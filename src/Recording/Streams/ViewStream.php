<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Recording\Streams;

use CyrildeWit\EloquentViewable\Data\ViewRecord;
use CyrildeWit\EloquentViewable\Recording\Streams\Contracts\StreamClient;
use Generator;

/** @internal */
final class ViewStream
{
    private bool $groupReady = false;

    public function __construct(
        private readonly StreamClient $client,
        private readonly string $stream,
        private readonly string $group,
        private readonly string $consumer = 'flusher',
        private readonly int $claimAfter = 60_000,
        private readonly int $pageSize = 1000,
    ) {}

    public function append(ViewRecord $record): void
    {
        $this->client->add($this->stream, StreamEntry::encode($record));
    }

    /** @param  iterable<ViewRecord>  $records */
    public function appendMany(iterable $records): void
    {
        $batch = [];

        foreach ($records as $record) {
            $batch[] = StreamEntry::encode($record);
        }

        if ($batch !== []) {
            $this->client->addMany($this->stream, $batch);
        }
    }

    /** @return list<StreamEntry> */
    public function take(int $limit): array
    {
        $this->ensureGroup();

        $entries = $this->client->claim($this->stream, $this->group, $this->consumer, $this->claimAfter, $limit);

        $remaining = $limit - count($entries);

        if ($remaining > 0) {
            return [...$entries, ...$this->client->read($this->stream, $this->group, $this->consumer, $remaining)];
        }

        return $entries;
    }

    /** @param  list<string>  $ids */
    public function acknowledge(array $ids): void
    {
        if ($ids === []) {
            return;
        }

        $this->client->acknowledge($this->stream, $this->group, $ids);
        $this->client->delete($this->stream, $ids);
    }

    /** @return Generator<int, StreamEntry> */
    public function entries(): Generator
    {
        $after = null;

        do {
            $page = $this->client->range($this->stream, $after, $this->pageSize);

            foreach ($page as $entry) {
                yield $entry;

                $after = $entry->id;
            }
        } while (count($page) === $this->pageSize);
    }

    /** @param  list<string>  $ids */
    public function delete(array $ids): void
    {
        if ($ids === []) {
            return;
        }

        $this->client->delete($this->stream, $ids);
    }

    /**
     * The group starts at id 0, so entries appended before it existed are
     * delivered as well.
     */
    private function ensureGroup(): void
    {
        if ($this->groupReady) {
            return;
        }

        $this->client->createGroup($this->stream, $this->group);

        $this->groupReady = true;
    }
}
