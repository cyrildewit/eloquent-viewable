<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Tests\Fixtures\Dimensions;

use CyrildeWit\EloquentViewable\Dimensions\Dimension;
use CyrildeWit\EloquentViewable\Dimensions\DimensionInput;

/**
 * The plan of the signed-in viewer, kept in `context` by default, as a small
 * application would before it adds a column.
 */
final class PlanDimension extends Dimension
{
    public function __construct(
        bool $personal = false,
        ?int $maxValues = self::MaxValues,
        ?string $json = 'context->plan',
    ) {
        parent::__construct($personal, $maxValues, $json);
    }

    public function resolve(DimensionInput $input): ?string
    {
        $plan = $input->viewable?->getAttribute('title');

        return is_string($plan) ? "plan-of-{$plan}" : null;
    }
}
