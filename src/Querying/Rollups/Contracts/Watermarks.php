<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Querying\Rollups\Contracts;

use Carbon\CarbonInterface;

/**
 * Implement this to tell how far rollups have folded the views. Retention asks
 * before it destroys anything, so no view is anonymised or deleted before
 * every rollup that needs it has been folded.
 */
interface Watermarks
{
    /**
     * It returns the cutoff moved back to where every rollup has folded the
     * views before it, or the cutoff itself when nothing waits on them.
     */
    public function clamp(CarbonInterface $cutoff): CarbonInterface;
}
