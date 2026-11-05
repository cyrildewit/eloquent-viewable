<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Tests\Fixtures\Dimensions;

use CyrildeWit\EloquentViewable\Dimensions\Dimension;
use CyrildeWit\EloquentViewable\Dimensions\DimensionInput;
use RuntimeException;

final class BrokenDimension extends Dimension
{
    public function resolve(DimensionInput $input): ?string
    {
        throw new RuntimeException('The lookup is down.');
    }
}
