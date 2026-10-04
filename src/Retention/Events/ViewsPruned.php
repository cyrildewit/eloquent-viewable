<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Retention\Events;

use Carbon\CarbonInterface;

/** Dispatched once views viewed in `[from, until)` are deleted, `from` being null on the first run. */
class ViewsPruned
{
    public function __construct(
        public ?CarbonInterface $from,
        public CarbonInterface $until,
        public int $views,
    ) {}
}
