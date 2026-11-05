<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Retention\Events;

use Carbon\CarbonInterface;

class ViewsAnonymised
{
    public function __construct(
        public ?CarbonInterface $from,
        public CarbonInterface $until,
        public int $views,
    ) {}
}
