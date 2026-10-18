<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Querying\Rollups\Contracts;

use Carbon\CarbonInterface;

/**
 * Implement this to fold the rollups again once views were deleted out of
 * buckets they already hold, so the rollups stop counting them.
 */
interface Refolder
{
    /**
     * The moment before which a rollup can no longer be folded again, because
     * the views it was folded from are pruned or anonymised. Null when every
     * view can still be folded again.
     */
    public function floor(): ?CarbonInterface;

    public function refold(CarbonInterface $from): void;
}
