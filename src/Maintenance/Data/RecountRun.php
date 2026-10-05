<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Maintenance\Data;

/**
 * How many models of each class a run recounted. A run that reached its
 * deadline is stopped, and the next run carries on where it left off.
 */
final readonly class RecountRun
{
    /** @param  array<class-string, int>  $models */
    public function __construct(
        public array $models,
        public bool $stopped,
    ) {}
}
