<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Recording\Events;

use CyrildeWit\EloquentViewable\Recording\Data\ViewAttempt;

/**
 * Dispatched once when `IgnoreBursts` starts refusing a visitor, with the
 * attempt that crossed the limit and what the burst was counted per. The
 * refusals that follow during `recording.bursts.block_for` only dispatch
 * `ViewSkipped`.
 */
final readonly class BurstDetected
{
    /** @param  'visitor'|'network'  $by */
    public function __construct(
        public ViewAttempt $attempt,
        public string $by,
    ) {}
}
