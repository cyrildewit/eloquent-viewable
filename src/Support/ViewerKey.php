<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Support;

use CyrildeWit\EloquentViewable\Exceptions\InvalidViewer;
use Illuminate\Database\Eloquent\Model;

/** @internal */
final class ViewerKey
{
    /** @throws InvalidViewer */
    public static function of(Model $viewer): int|string
    {
        $key = $viewer->getKey();

        if (is_int($key) || is_string($key)) {
            return $key;
        }

        throw InvalidViewer::unsupportedKey($viewer::class, $key);
    }
}
