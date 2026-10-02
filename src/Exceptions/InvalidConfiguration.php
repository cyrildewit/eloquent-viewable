<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Exceptions;

use CyrildeWit\EloquentViewable\Models\View;
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

    public static function mustBeStringOrNull(string $key, mixed $value): self
    {
        return new self("The `eloquent-viewable.{$key}` config value must be a string or null, ".self::describe($value).' given.');
    }

    public static function mustBeListOfStrings(string $key, mixed $value): self
    {
        return new self("The `eloquent-viewable.{$key}` config value must be a list of strings, ".self::describe($value).' given.');
    }

    public static function mustBeViewModel(string $key, mixed $value): self
    {
        return new self("The `eloquent-viewable.{$key}` config value must be the name of a class that extends `".View::class.'`, '.self::describe($value).' given.');
    }

    public static function unknownDriver(string $key, string $driver): self
    {
        return new self("The `eloquent-viewable.{$key}` config value names a driver that is not registered, `{$driver}` given.");
    }

    public static function mustBeListOfClasses(string $key, mixed $value): self
    {
        return new self("The `eloquent-viewable.{$key}` config value must be a list of class names, ".self::describe($value).' given.');
    }

    public static function mustImplement(string $key, string $interface, string $class): self
    {
        return new self("Every class in `eloquent-viewable.{$key}` must implement `{$interface}`, `{$class}` does not.");
    }

    /** @param  list<string>  $allowed */
    public static function mustBeOneOf(string $key, array $allowed, mixed $value): self
    {
        return new self("The `eloquent-viewable.{$key}` config value must be one of `".implode('`, `', $allowed).'`, '.self::describe($value).' given.');
    }

    public static function missingAppKey(): self
    {
        return new self('The `app.key` config value must be set to derive visitor ids from viewers.');
    }

    private static function describe(mixed $value): string
    {
        return is_scalar($value) ? '`'.json_encode($value).'`' : get_debug_type($value);
    }
}
