<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Retention\Events;

use Carbon\CarbonInterface;

/** Dispatched once views viewed in `[from, until)` are anonymised, `from` being null on the first run. */
class ViewsAnonymised
{
    public function __construct(
        public ?CarbonInterface $from,
        public CarbonInterface $until,
        public int $views,
    ) {}
}
