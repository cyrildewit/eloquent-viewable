<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Dimensions;

/**
 * Every value is cleaned up here when it is written, so a read never has to.
 */
final class Normaliser
{
    public const int MaxLength = 64;

    /**
     * The value rollups store for the views folded away by the cap. It starts
     * with a control character, which no normalised value can hold, so it
     * never clashes with a real value.
     */
    public const string Other = "\x1Fother";

    /**
     * Trims the value, drops control characters and invalid UTF-8, and cuts
     * it at 64 characters. An empty value becomes null.
     */
    public static function normalise(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = (string) preg_replace('/\p{Cc}/u', '', mb_scrub($value, 'UTF-8'));
        $value = trim(mb_substr(trim($value), 0, self::MaxLength));

        if ($value === '') {
            return null;
        }

        return $value;
    }
}
