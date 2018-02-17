<?php

declare(strict_types=1);

/*
 * This file is part of Eloquent Visitable.
 *
 * (c) Cyril de Wit <github@cyrildewit.nl>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace CyrildeWit\EloquentVisitable\Helpers;

use Carbon\Carbon;

/**
 * This is the date transformer service.
 *
 * @author Cyril de Wit <github@cyrildewit.nl>
 */
class DateTransformer
{
    /**
     * Transform the given value to a date based on the defined transformers.
     *
     * @param  string  $key
     * @return \Carbon\Carbon
     */
    public function transform($key): Carbon
    {
        if (! is_string($key) && $key instanceof Carbon) {
            return $key;
        }

        $transformers = collect(config('eloquent-visitable.date-transformers', []));

        return $transformers->get($key);
    }
}
