<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Recording\Stores;

use Closure;
use CyrildeWit\EloquentViewable\Contracts\Viewable;
use CyrildeWit\EloquentViewable\Data\ViewRecord;
use CyrildeWit\EloquentViewable\Recording\Contracts\BufferedViewStore;
use CyrildeWit\EloquentViewable\Recording\Contracts\ViewStore;
use CyrildeWit\EloquentViewable\Recording\Data\StreamBacklog;
use CyrildeWit\EloquentViewable\Recording\Streams\ViewStream;

final readonly class RedisStreamStore implements BufferedViewStore
{
    public function __construct(
        private ViewStream $stream,
        private ViewStore $landing,
    ) {}

    public function store(ViewRecord $record): void
    {
        $this->stream->append($record);
    }

    /** @param  iterable<ViewRecord>  $records */
    public function storeMany(iterable $records): void
    {
        $this->stream->appendMany($records);
    }

    /**
     * A stream cannot delete by field value, so the entries of the viewable
     * are found by scanning what has not landed yet.
     */
    public function forget(Viewable $viewable): void
    {
        $ids = [];

        foreach ($this->stream->entries() as $entry) {
            if (! $entry->isEmpty() && $entry->record()->belongsTo($viewable)) {
                $ids[] = $entry->id;
            }
        }

        $this->stream->delete($ids);

        $this->landing->forget($viewable);
    }

    public function backlog(): StreamBacklog
    {
        return $this->stream->backlog();
    }

    /**
     * The entries are found by scanning what has not landed yet, like
     * forget() does. A range only returns entries that still hold their
     * fields. They are inserted before they are deleted, so a crash in between
     * lands them twice rather than not at all.
     *
     * @param  Closure(ViewRecord): bool  $filter
     */
    public function land(Closure $filter): int
    {
        $ids = [];
        $records = [];

        foreach ($this->stream->entries() as $entry) {
            $record = $entry->record();

            if (! $filter($record)) {
                continue;
            }

            $ids[] = $entry->id;
            $records[] = $record;
        }

        if ($records !== []) {
            $this->landing->storeMany($records);
        }

        $this->stream->delete($ids);

        return count($records);
    }

    /**
     * Delivery is at least once. The insert and the acknowledgement are the
     * last two steps, so a crash between them lands the batch again.
     */
    public function flush(int $limit = 1000): int
    {
        $entries = $this->stream->take($limit);

        if ($entries === []) {
            return 0;
        }

        $ids = [];
        $records = [];

        foreach ($entries as $entry) {
            $ids[] = $entry->id;

            if (! $entry->isEmpty()) {
                $records[] = $entry->record();
            }
        }

        if ($records !== []) {
            $this->landing->storeMany($records);
        }

        $this->stream->acknowledge($ids);

        return count($records);
    }
}
