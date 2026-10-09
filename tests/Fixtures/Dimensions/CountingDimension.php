<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Tests\Fixtures\Dimensions;

use CyrildeWit\EloquentViewable\Dimensions\Dimension;
use CyrildeWit\EloquentViewable\Dimensions\DimensionInput;

/**
 * Counts how often it is asked, so a test can tell whether a view resolved
 * its dimensions at all.
 */
final class CountingDimension extends Dimension
{
    public static int $resolved = 0;

    public function resolve(DimensionInput $input): string
    {
        self::$resolved++;

        return 'counted';
    }
}
