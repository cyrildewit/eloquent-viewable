<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Support;

/**
 * An anonymised view keeps a visitor id that starts with this prefix, re-hashed
 * under a salt of its day, so the id no longer links a visitor across days.
 */
final class AnonymisedVisitor
{
    public const string Prefix = 'a:';

    public static function hash(string $visitor, string $salt): string
    {
        return self::Prefix.hash_hmac('sha256', $visitor, $salt);
    }

    public static function isAnonymised(string $visitor): bool
    {
        return str_starts_with($visitor, self::Prefix);
    }
}
