<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Support;

use CyrildeWit\EloquentViewable\Contracts\Viewable;
use CyrildeWit\EloquentViewable\Exceptions\InvalidViewable;

/** @internal */
final class ViewableKey
{
    /** @throws InvalidViewable */
    public static function of(Viewable $viewable): int|string|null
    {
        $key = $viewable->getKey();

        if ($key === null || is_int($key) || is_string($key)) {
            return $key;
        }

        throw InvalidViewable::unsupportedKey($viewable::class, $key);
    }
}
