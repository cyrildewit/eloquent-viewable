<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Retention\Events;

use Carbon\CarbonInterface;

/** Dispatched once the views of bots viewed in `[from, until)` are deleted. */
final readonly class BotViewsPurged
{
    public function __construct(
        public CarbonInterface $from,
        public CarbonInterface $until,
        public int $views,
        public int $visitors,
    ) {}
}
