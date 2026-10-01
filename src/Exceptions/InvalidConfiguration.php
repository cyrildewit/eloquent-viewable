<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Exceptions;

use Exception;

final class InvalidConfiguration extends Exception implements EloquentViewableException
{
    public static function mustBePositiveInteger(string $key, mixed $value): self
    {
        return new self("The `eloquent-viewable.{$key}` config value must be a positive integer, ".self::describe($value).' given.');
    }

    public static function mustBeNonEmptyString(string $key, mixed $value): self
    {
        return new self("The `eloquent-viewable.{$key}` config value must be a non-empty string, ".self::describe($value).' given.');
    }

    private static function describe(mixed $value): string
    {
        return is_scalar($value) ? '`'.json_encode($value).'`' : get_debug_type($value);
    }
}
