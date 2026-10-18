<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Exceptions;

use CyrildeWit\EloquentViewable\Models\View;
use Exception;
use Throwable;

final class InvalidConfiguration extends Exception implements EloquentViewableException
{
    public static function mustBePositiveInteger(string $key, mixed $value): self
    {
        return self::must($key, 'be a positive integer', $value);
    }

    public static function mustBePositiveIntegerOrNull(string $key, mixed $value): self
    {
        return self::must($key, 'be a positive integer or null', $value);
    }

    public static function mustBeNonEmptyString(string $key, mixed $value): self
    {
        return self::must($key, 'be a non-empty string', $value);
    }

    public static function mustBeStringOrNull(string $key, mixed $value): self
    {
        return self::must($key, 'be a string or null', $value);
    }

    public static function mustBeListOfStrings(string $key, mixed $value): self
    {
        return self::must($key, 'be a list of strings', $value);
    }

    public static function mustBeViewModel(string $key, mixed $value): self
    {
        return self::must($key, 'be the name of a class that extends `'.View::class.'`', $value);
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
        return self::must($key, 'be a list of class names', $value);
    }

    public static function mustImplement(string $key, string $interface, string $class): self
    {
        return new self("Every class in `eloquent-viewable.{$key}` must implement `{$interface}`, `{$class}` does not.");
    }

    /** @param  list<string>  $allowed */
    public static function mustBeOneOf(string $key, array $allowed, mixed $value): self
    {
        return self::must($key, 'be one of `'.implode('`, `', $allowed).'`', $value);
    }

    public static function mustBeDuration(string $key, mixed $value, bool $nullable = true): self
    {
        return self::must($key, 'be a duration such as `30d` or `2y`'.($nullable ? ', or null' : ''), $value);
    }

    /** @param  list<string>  $allowed */
    public static function mustBeSubsetOf(string $key, array $allowed, mixed $value): self
    {
        return self::must($key, 'be a list of `'.implode('`, `', $allowed).'`', $value);
    }

    public static function anonymisedAfterPruned(string $anonymise, string $prune): self
    {
        return new self("The `eloquent-viewable.retention.anonymise.after` config value must not be longer than `retention.prune.after`, `{$anonymise}` and `{$prune}` given. Views are deleted before they would be anonymised.");
    }

    public static function mustBeTiers(string $key, mixed $value): self
    {
        return self::must($key, 'map `hour`, `day`, `month` or `year` to a duration or null', $value);
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
        return self::must($key, 'map viewable model classes to their counter columns, each listed by name or mapped to options of `unique`, `period`, `collection`, `hot` and `dimensions`', $value);
    }

    public static function mustBeMilestones(string $key, mixed $value): self
    {
        return self::must($key, 'map viewable model classes to their counter columns, each with a list of thresholds in ascending order', $value);
    }

    public static function milestoneWithoutCounter(string $class, string $column): self
    {
        return self::milestoneNames($class, $column, 'is not one of its counter columns in `querying.counters`');
    }

    public static function milestoneOnPeriod(string $class, string $column): self
    {
        return self::milestoneNames($class, $column, 'counts a period. A count over a period goes up and down, so it cannot cross a milestone once');
    }

    public static function mustBeHotScore(string $column, mixed $value): self
    {
        $given = self::describe($value);

        return new self("The `hot` option of the `{$column}` counter column in `eloquent-viewable.querying.counters` must be `true`, the name of a timestamp column, or `from` and `every` options such as `['from' => 'published_at', 'every' => '12h']`, {$given} given.");
    }

    public static function milestoneOnHotScore(string $class, string $column): self
    {
        return self::milestoneNames($class, $column, 'holds a hot score rather than a count');
    }

    public static function withoutHotScore(string $class): self
    {
        return new self("`{$class}` has no counter column with the `hot` option in `eloquent-viewable.querying.counters`, so orderByHot() has nothing to order by.");
    }

    public static function mustBeSpikes(string $key, mixed $value): self
    {
        return self::must($key, 'map viewable model classes to options of `window`, `seasonality`, `samples`, `threshold`, `minimum`, `drops` and `cooldown`', $value);
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
        return self::must($key, 'be the name of a class, or null', $value);
    }

    public static function mustNameClassImplementing(string $key, string $interface, string $class): self
    {
        return new self("The `eloquent-viewable.{$key}` config value must name a class implementing `{$interface}`, `{$class}` does not.");
    }

    public static function mustBeShare(string $key, mixed $value): self
    {
        return self::must($key, 'be a number above 0 and at most 1', $value);
    }

    public static function invalidDimension(string $name, string $problem, ?Throwable $previous = null): self
    {
        return new self("The `{$name}` entry in `eloquent-viewable.dimensions.definitions` {$problem}.", previous: $previous);
    }

    public static function mustBeDimensions(string $key, mixed $value): self
    {
        return self::must($key, 'map dimension names to a class name, or to an array of the class name and its options', $value);
    }

    public static function mustBeSourceList(string $key, mixed $value): self
    {
        return self::must($key, "map hosts to a pair of a source name and a medium, such as `['Example', 'referral']`", $value);
    }

    public static function mustMapStrings(string $key, mixed $value): self
    {
        return self::must($key, 'map strings to strings', $value);
    }

    public static function unknownDimension(string $key, string $name): self
    {
        return new self("The `eloquent-viewable.{$key}` config value names `{$name}`, which is not listed in `dimensions.definitions`.");
    }

    public static function dimensionsWithoutTiers(): self
    {
        return new self('The `eloquent-viewable.retention.rollups.dimensions` config value folds dimensions, but `retention.rollups.tiers` keeps no tier to fold them into.');
    }

    private static function must(string $key, string $expectation, mixed $value): self
    {
        $given = self::describe($value);

        return new self("The `eloquent-viewable.{$key}` config value must {$expectation}, {$given} given.");
    }

    private static function milestoneNames(string $class, string $column, string $problem): self
    {
        return new self("The `eloquent-viewable.milestones.thresholds` config value names the `{$column}` column of `{$class}`, which {$problem}.");
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
