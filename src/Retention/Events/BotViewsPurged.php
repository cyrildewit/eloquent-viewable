<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Retention\Events;

use Carbon\CarbonInterface;

final readonly class BotViewsPurged
{
    public function __construct(
        public CarbonInterface $from,
        public CarbonInterface $until,
        public int $views,
        public int $visitors,
    ) {}
}
