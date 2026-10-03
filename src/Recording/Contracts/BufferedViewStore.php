<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Recording\Contracts;

interface BufferedViewStore extends ViewStore
{
    /** Returns how many views landed; zero when the buffer is empty. */
    public function flush(int $limit = 1000): int;
}
