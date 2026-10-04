<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Exceptions;

use CyrildeWit\EloquentViewable\Models\View;
use Exception;

final class InvalidConfiguration extends Exception implements EloquentViewableException
{
    public static function mustBePositiveInteger(string $key, mixed $value): self
    {
        $given = self::describe($value);

        return new self("The `eloquent-viewable.{$key}` config value must be a positive integer, {$given} given.");
    }

    public static function mustBePositiveIntegerOrNull(string $key, mixed $value): self
    {
        $given = self::describe($value);

        return new self("The `eloquent-viewable.{$key}` config value must be a positive integer or null, {$given} given.");
    }

    public static function mustBeNonEmptyString(string $key, mixed $value): self
    {
        $given = self::describe($value);

        return new self("The `eloquent-viewable.{$key}` config value must be a non-empty string, {$given} given.");
    }

    public static function mustBeStringOrNull(string $key, mixed $value): self
    {
        $given = self::describe($value);

        return new self("The `eloquent-viewable.{$key}` config value must be a string or null, {$given} given.");
    }

    public static function mustBeListOfStrings(string $key, mixed $value): self
    {
        $given = self::describe($value);

        return new self("The `eloquent-viewable.{$key}` config value must be a list of strings, {$given} given.");
    }

    public static function mustBeViewModel(string $key, mixed $value): self
    {
        $view = View::class;

        $given = self::describe($value);

        return new self("The `eloquent-viewable.{$key}` config value must be the name of a class that extends `{$view}`, {$given} given.");
    }

    public static function unknownDriver(string $key, string $driver): self
    {
        return new self("The `eloquent-viewable.{$key}` config value names a driver that is not registered, `{$driver}` given.");
    }

    public static function mustNameAnotherDriver(string $key, string $driver): self
    {
        return new self("The `eloquent-viewable.{$key}` config value must name a driver other than `{$driver}`, which would land its views in itself.");
    }

    public static function mustBeListOfClasses(string $key, mixed $value): self
    {
        $given = self::describe($value);

        return new self("The `eloquent-viewable.{$key}` config value must be a list of class names, {$given} given.");
    }

    public static function mustImplement(string $key, string $interface, string $class): self
    {
        return new self("Every class in `eloquent-viewable.{$key}` must implement `{$interface}`, `{$class}` does not.");
    }

    /** @param  list<string>  $allowed */
    public static function mustBeOneOf(string $key, array $allowed, mixed $value): self
    {
        $options = implode('`, `', $allowed);

        $given = self::describe($value);

        return new self("The `eloquent-viewable.{$key}` config value must be one of `{$options}`, {$given} given.");
    }

    public static function mustBeDuration(string $key, mixed $value): self
    {
        $given = self::describe($value);

        return new self("The `eloquent-viewable.{$key}` config value must be a duration such as `30d` or `2y`, or null, {$given} given.");
    }

    /** @param  list<string>  $allowed */
    public static function mustBeSubsetOf(string $key, array $allowed, mixed $value): self
    {
        $options = implode('`, `', $allowed);
        $given = self::describe($value);

        return new self("The `eloquent-viewable.{$key}` config value must be a list of `{$options}`, {$given} given.");
    }

    public static function anonymisedAfterPruned(string $anonymise, string $prune): self
    {
        return new self("The `eloquent-viewable.retention.anonymise.after` config value must not be longer than `retention.prune.after`, `{$anonymise}` and `{$prune}` given. Views are deleted before they would be anonymised.");
    }

    public static function mustBeTiers(string $key, mixed $value): self
    {
        $given = self::describe($value);

        return new self("The `eloquent-viewable.{$key}` config value must map `hour`, `day`, `month` or `year` to a duration or null, {$given} given.");
    }

    public static function coarserTierKeptShorter(string $rollup, string $coarser, string $finer): self
    {
        return new self("The `{$coarser}` tier of the `{$rollup}` rollup must be kept at least as long as the finer `{$finer}` tier, because a finer tier only expires once a coarser one has captured it.");
    }

    public static function invalidRollup(string $class, string $problem): self
    {
        return new self("The `{$class}` rollup in `eloquent-viewable.retention.rollups.custom` {$problem}.");
    }

    public static function prunedBeforeFolded(string $prune, string $tier, string $settle): self
    {
        return new self("The `eloquent-viewable.retention.prune.after` config value must be longer than one `{$tier}` plus `retention.rollups.settle`, `{$prune}` and `{$settle}` given. A `{$tier}` bucket can only be folded while its views still exist.");
    }

    public static function mustBeCounters(string $key, mixed $value): self
    {
        $given = self::describe($value);

        return new self("The `eloquent-viewable.{$key}` config value must map viewable model classes to their counter columns, each listed by name or mapped to options of `unique`, `period` and `collection`, {$given} given.");
    }

    public static function invalidMiddlewareOption(string $option): self
    {
        return new self("The `views` middleware does not understand `{$option}`. It takes `collection=<name>`, `cooldown=<minutes>` and `queue=<true|false>`.");
    }

    public static function beaconDisabled(): self
    {
        return new self('The beacon route is not registered. Set `eloquent-viewable.recording.beacon.enabled` to `true` to record views from the browser.');
    }

    private static function describe(mixed $value): string
    {
        if (! is_scalar($value)) {
            return get_debug_type($value);
        }

        $json = json_encode($value);

        return "`{$json}`";
    }
}
