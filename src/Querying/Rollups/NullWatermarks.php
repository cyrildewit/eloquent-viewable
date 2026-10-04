<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Querying\Rollups;

use Carbon\CarbonInterface;
use CyrildeWit\EloquentViewable\Querying\Rollups\Contracts\Watermarks;

/**
 * Bound while no rollups are kept, so retention runs on its own.
 */
final readonly class NullWatermarks implements Watermarks
{
    public function clamp(CarbonInterface $cutoff): CarbonInterface
    {
        return $cutoff;
    }
}
