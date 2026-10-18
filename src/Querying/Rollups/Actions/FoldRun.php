<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Querying\Rollups\Actions;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use CyrildeWit\EloquentViewable\Support\Deadline;

/**
 * What one run of the fold shares across every tier it folds: the moment
 * it started, where to fold again from, the ids the last run saw and this
 * one sees, and whether it only counts.
 *
 * @internal
 */
final readonly class FoldRun
{
    public function __construct(
        public CarbonImmutable $now,
        public ?CarbonInterface $from,
        public ?int $lastId,
        public ?int $maxId,
        public Deadline $deadline,
        public bool $dryRun,
    ) {}
}
