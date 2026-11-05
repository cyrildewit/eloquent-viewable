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
     * The cutoff, moved back to where every rollup has folded the views before
     * it. The cutoff itself when no rollup waits on the views.
     */
    public function clamp(CarbonInterface $cutoff): CarbonInterface;

    /**
     * These watermarks as they will stand once a run has folded every rollup
     * up to now, so a dry run that folds nothing first can tell how far the
     * real run would get.
     */
    public function afterFolding(): self;
}
