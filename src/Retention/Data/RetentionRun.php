<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Retention\Data;

use Carbon\CarbonInterface;

/**
 * A run of anonymising or pruning covers the views viewed in `[from, until)`,
 * `from` being null on the first run. It is clamped when a rollup had not yet
 * folded the views up to the cutoff that was asked for.
 */
final readonly class RetentionRun
{
    public function __construct(
        public ?CarbonInterface $from,
        public CarbonInterface $until,
        public int $views,
        public bool $clamped,
        public bool $dryRun,
    ) {}
}
