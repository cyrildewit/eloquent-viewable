<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Recording\Buffering;

use CyrildeWit\EloquentViewable\Recording\Contracts\BufferedViewStore;
use CyrildeWit\EloquentViewable\Recording\Contracts\ViewStore;
use CyrildeWit\EloquentViewable\Recording\Exceptions\StoreIsNotBuffered;

final readonly class Flusher
{
    public function __construct(private ViewStore $store) {}

    /** @throws StoreIsNotBuffered */
    public function flush(int $batch = 1000): int
    {
        if (! $this->store instanceof BufferedViewStore) {
            throw StoreIsNotBuffered::forStore($this->store);
        }

        $total = 0;

        while (($landed = $this->store->flush($batch)) > 0) {
            $total += $landed;
        }

        return $total;
    }
}
