<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Support;

/**
 * How far a run of chunks got: how many rows it applied, and whether the
 * deadline stopped it before the rest.
 *
 * @internal
 */
final readonly class Progress
{
    public function __construct(
        public int $count,
        public bool $stopped,
    ) {}
}
