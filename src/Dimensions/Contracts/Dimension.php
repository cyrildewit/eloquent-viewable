<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Dimensions\Contracts;

use CyrildeWit\EloquentViewable\Dimensions\DimensionInput;
use CyrildeWit\EloquentViewable\Dimensions\Storage;

/**
 * Implement this to count views by a value of your own. Extending
 * `Dimensions\Dimension` gives every method but `resolve()` a default.
 */
interface Dimension
{
    /**
     * The value for this view, or null for none. It is trimmed, stripped of
     * control characters and cut at 64 characters after this returns.
     */
    public function resolve(DimensionInput $input): ?string;

    public function storage(): Storage;

    /**
     * How many values a rollup bucket keeps before it folds the rest into
     * `other`, or null to keep every value.
     */
    public function maxValues(): ?int;

    /**
     * Whether the value can identify a person. Anonymising sets a personal
     * value to null.
     */
    public function personal(): bool;
}
