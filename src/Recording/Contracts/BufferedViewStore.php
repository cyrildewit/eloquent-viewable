<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Recording\Contracts;

use Closure;
use CyrildeWit\EloquentViewable\Data\ViewRecord;

interface BufferedViewStore extends ViewStore
{
    /** Returns how many views landed; zero when the buffer is empty. */
    public function flush(int $limit = 1000): int;

    /**
     * Land the buffered views the filter keeps ahead of the next flush, so a
     * query on the views table finds them. Returns how many views landed.
     *
     * @param  Closure(ViewRecord): bool  $filter
     */
    public function land(Closure $filter): int;
}
