<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Retention\Data;

use Carbon\CarbonInterface;

/**
 * A purge covers the views viewed in `[from, until)`. It is clamped when the
 * requested start lay before the moment the rollups can be folded again from,
 * because deleting views before it would leave the rollups counting them.
 */
final readonly class PurgeRun
{
    public function __construct(
        public CarbonInterface $from,
        public CarbonInterface $until,
        public int $views,
        public int $visitors,
        public bool $clamped,
        public bool $dryRun,
    ) {}
}
