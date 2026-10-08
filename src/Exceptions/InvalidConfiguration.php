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

    public static function mustBeDuration(string $key, mixed $value, bool $nullable = true): self
    {
        $given = self::describe($value);
        $null = $nullable ? ', or null' : '';

        return new self("The `eloquent-viewable.{$key}` config value must be a duration such as `30d` or `2y`{$null}, {$given} given.");
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

        return new self("The `eloquent-viewable.{$key}` config value must map viewable model classes to their counter columns, each listed by name or mapped to options of `unique`, `period`, `collection` and `hot`, {$given} given.");
    }

    public static function mustBeMilestones(string $key, mixed $value): self
    {
        $given = self::describe($value);

        return new self("The `eloquent-viewable.{$key}` config value must map viewable model classes to their counter columns, each with a list of thresholds in ascending order, {$given} given.");
    }

    public static function milestoneWithoutCounter(string $class, string $column): self
    {
        return new self("The `eloquent-viewable.milestones.thresholds` config value names the `{$column}` column of `{$class}`, which is not one of its counter columns in `querying.counters`.");
    }

    public static function milestoneOnPeriod(string $class, string $column): self
    {
        return new self("The `eloquent-viewable.milestones.thresholds` config value names the `{$column}` column of `{$class}`, which counts a period. A count over a period goes up and down, so it cannot cross a milestone once.");
    }

    public static function mustBeHotScore(string $column, mixed $value): self
    {
        $given = self::describe($value);

        return new self("The `hot` option of the `{$column}` counter column in `eloquent-viewable.querying.counters` must be `true`, the name of a timestamp column, or `from` and `every` options such as `['from' => 'published_at', 'every' => '12h']`, {$given} given.");
    }

    public static function milestoneOnHotScore(string $class, string $column): self
    {
        return new self("The `eloquent-viewable.milestones.thresholds` config value names the `{$column}` column of `{$class}`, which holds a hot score rather than a count.");
    }

    public static function withoutHotScore(string $class): self
    {
        return new self("`{$class}` has no counter column with the `hot` option in `eloquent-viewable.querying.counters`, so orderByHot() has nothing to order by.");
    }

    public static function mustBeSpikes(string $key, mixed $value): self
    {
        $given = self::describe($value);

        return new self("The `eloquent-viewable.{$key}` config value must map viewable model classes to options of `window`, `seasonality`, `samples`, `threshold`, `minimum`, `drops` and `cooldown`, {$given} given.");
    }

    public static function invalidSpikeOption(string $class, string $option, string $expected, mixed $value): self
    {
        $given = self::describe($value);

        return new self("The `{$option}` option of `{$class}` in `eloquent-viewable.spikes.types` must be {$expected}, {$given} given.");
    }

    public static function invalidMiddlewareOption(string $option): self
    {
        return new self("The `views` middleware does not understand `{$option}`. It takes `collection=<name>`, `cooldown=<minutes>` and `queue=<true|false>`.");
    }

    public static function beaconDisabled(): self
    {
        return new self('The beacon route is not registered. Set `eloquent-viewable.recording.beacon.enabled` to `true` to record views from the browser.');
    }

    public static function presenceDisabled(): self
    {
        return new self('Presence is not kept. Set `eloquent-viewable.presence.enabled` to `true` to count the visitors who are looking right now.');
    }

    public static function presenceViewersDisabled(): self
    {
        return new self('The signed-in viewers are not kept. Set `eloquent-viewable.presence.viewers` to `true` to list who is looking right now.');
    }

    public static function mustBeClassOrNull(string $key, mixed $value): self
    {
        $given = self::describe($value);

        return new self("The `eloquent-viewable.{$key}` config value must be the name of a class, or null, {$given} given.");
    }

    public static function mustNameClassImplementing(string $key, string $interface, string $class): self
    {
        return new self("The `eloquent-viewable.{$key}` config value must name a class implementing `{$interface}`, `{$class}` does not.");
    }

    public static function mustBeShare(string $key, mixed $value): self
    {
        $given = self::describe($value);

        return new self("The `eloquent-viewable.{$key}` config value must be a number above 0 and at most 1, {$given} given.");
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
