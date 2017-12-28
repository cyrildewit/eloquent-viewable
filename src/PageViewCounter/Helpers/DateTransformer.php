<?php

namespace CyrildeWit\PageViewCounter\Helpers;

class DateTransformer
{
    /**
     * Transform the given value to a date based on the defined transformers.
     *
     * @param  \Carbon\Carbon|string  $value
     * @return \Carbon\Carbon
     */
    public static function transform($value)
    {
        $transformers = collect(config('page-view-counter.date-transformers'));

        if ($transformers->isEmpty() || ! $transformers->has((string) $value)) {
            return $value;
        }

        return $transformers->get($value);
    }
}
