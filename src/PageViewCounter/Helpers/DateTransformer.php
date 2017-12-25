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
        $transformers = config('page-view-counter.date-transformers');

        foreach ($transformers as $key => $transformer) {
            if ($key === $date) {
                return $transformer;
            }
        }

        return $value;
    }
}
