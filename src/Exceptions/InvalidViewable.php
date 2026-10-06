<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Exceptions;

use CyrildeWit\EloquentViewable\Contracts\Viewable;
use CyrildeWit\EloquentViewable\Support\ViewableKey;
use InvalidArgumentException;

final class InvalidViewable extends InvalidArgumentException implements EloquentViewableException
{
    public static function classDoesNotImplementViewable(string $class): self
    {
        $viewable = Viewable::class;

        return new self("Class [{$class}] must implement {$viewable}.");
    }

    public static function missing(): self
    {
        return new self('No viewable was given. Call forViewable() before counting, recording or destroying views.');
    }

    public static function cannotRankOne(Viewable $viewable): self
    {
        $class = $viewable::class;

        $key = ViewableKey::of($viewable);

        return new self("top() ranks every viewable of a type or every type. [{$class}] with key {$key} was given; pass a model without a key, or none at all.");
    }

    public static function cannotPairType(Viewable $viewable): self
    {
        $class = $viewable::class;

        return new self("alsoViewed() ranks what the visitors of one viewable also viewed. A [{$class}] without a key was given; pass a saved model.");
    }

    public static function missingSet(): self
    {
        return new self('No viewables were given. Call forViewables() before counting them.');
    }

    public static function setNeedsCounts(): self
    {
        return new self('A set of viewables is counted one by one. Call counts() instead of count().');
    }

    public static function missingKey(string $class): self
    {
        return new self("Every viewable in a set needs a key, an unsaved [{$class}] was given.");
    }

    public static function mixedTypes(string $expected, string $given): self
    {
        return new self("Every viewable in a set must be of one type, [{$expected}] and [{$given}] given.");
    }

    public static function unsupportedKey(string $class, mixed $key): self
    {
        $type = get_debug_type($key);

        return new self("The key of [{$class}] must be an integer, a string or null, {$type} given.");
    }

    public static function noneInRoute(string $uri): self
    {
        return new self("The route [{$uri}] binds no viewable model to record a view of.");
    }

    public static function notInRoute(string $selector, string $uri): self
    {
        return new self("The route [{$uri}] binds no viewable [{$selector}] to record a view of.");
    }

    public static function routeParameterNotViewable(string $parameter, string $uri): self
    {
        $viewable = Viewable::class;

        return new self("The parameter [{$parameter}] of the route [{$uri}] must be bound to a model that implements {$viewable}.");
    }

    public static function beaconWithoutKey(string $class): self
    {
        return new self("A beacon records a view of a saved model, an unsaved [{$class}] was given.");
    }
}
