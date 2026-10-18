<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Support;

use Closure;
use CyrildeWit\EloquentViewable\Contracts\Viewable;
use CyrildeWit\EloquentViewable\Exceptions\InvalidConfiguration;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Database\Eloquent\Model;

/**
 * What every typed reader of the package config file shares: the keys are
 * read under the `eloquent-viewable` prefix and checked as they are read.
 *
 * Defaults live in `config/eloquent-viewable.php` and are merged in by the
 * service provider, so they are not repeated here. A key that has to hold a
 * value throws when it is missing or empty, because there is no fallback that
 * would be correct; a boolean or list key falls back to the shipped default,
 * because there is.
 *
 * @internal
 */
abstract readonly class ConfigReader
{
    public function __construct(
        protected Repository $config,
    ) {}

    protected function bool(string $key): bool
    {
        return (bool) $this->get($key, false);
    }

    protected function get(string $key, mixed $default = null): mixed
    {
        return $this->config->get("eloquent-viewable.{$key}", $default);
    }

    /**
     * A single string is read as a list of one.
     *
     * @return list<string>
     *
     * @throws InvalidConfiguration
     */
    protected function strings(string $key): array
    {
        $value = (array) $this->get($key, []);

        foreach ($value as $item) {
            if (! is_string($item)) {
                throw InvalidConfiguration::mustBeListOfStrings($key, $item);
            }
        }

        /** @var list<string> */
        return array_values($value);
    }

    /** @throws InvalidConfiguration */
    protected function string(string $key): ?string
    {
        $value = $this->get($key);

        if ($value !== null && ! is_string($value)) {
            throw InvalidConfiguration::mustBeStringOrNull($key, $value);
        }

        return $value;
    }

    /** @throws InvalidConfiguration */
    protected function nonEmptyString(string $key): string
    {
        $value = $this->get($key);

        if (! is_string($value) || $value === '') {
            throw InvalidConfiguration::mustBeNonEmptyString($key, $value);
        }

        return $value;
    }

    /**
     * A string among the allowed ones, with the default for a key that is
     * not set.
     *
     * @template TOption of string
     *
     * @param  non-empty-list<TOption>  $allowed
     * @param  TOption  $default
     * @return TOption
     *
     * @throws InvalidConfiguration
     */
    protected function oneOf(string $key, array $allowed, string $default): string
    {
        $value = $this->get($key, $default);

        if (! in_array($value, $allowed, true)) {
            throw InvalidConfiguration::mustBeOneOf($key, $allowed, $value);
        }

        return $value;
    }

    /**
     * @return list<class-string>
     *
     * @throws InvalidConfiguration
     */
    protected function classes(string $key, mixed $default = null): array
    {
        $value = $this->get($key, $default);

        if (! is_array($value)) {
            throw InvalidConfiguration::mustBeListOfClasses($key, $value);
        }

        foreach ($value as $class) {
            if (! is_string($class) || ! class_exists($class)) {
                throw InvalidConfiguration::mustBeListOfClasses($key, $class);
            }
        }

        /** @var list<class-string> */
        return array_values($value);
    }

    /**
     * An array keyed by name. The exception is built for the value as a
     * whole, or for a key that is not a string.
     *
     * @param  Closure(string, mixed): InvalidConfiguration  $exception
     * @return array<string, mixed>
     *
     * @throws InvalidConfiguration
     */
    protected function map(string $key, Closure $exception): array
    {
        $value = $this->get($key, []);

        if (! is_array($value)) {
            throw $exception($key, $value);
        }

        $map = [];

        foreach ($value as $name => $entry) {
            if (! is_string($name)) {
                throw $exception($key, $name);
            }

            $map[$name] = $entry;
        }

        return $map;
    }

    /**
     * The class when it names a viewable model, or null.
     *
     * @return ?class-string<Model&Viewable>
     */
    protected function viewableClass(mixed $class): ?string
    {
        if (! is_string($class) || ! is_a($class, Model::class, true) || ! is_a($class, Viewable::class, true)) {
            return null;
        }

        return $class;
    }

    /**
     * @param  array<mixed>  $options
     * @param  list<string>  $allowed
     */
    protected function optionsAmong(array $options, array $allowed): bool
    {
        return array_diff(array_keys($options), $allowed) === [];
    }

    /** @throws InvalidConfiguration */
    protected function duration(string $key): ?Duration
    {
        $value = $this->get($key);

        if ($value === null) {
            return null;
        }

        if (! is_string($value)) {
            throw InvalidConfiguration::mustBeDuration($key, $value);
        }

        return Duration::tryParse($value) ?? throw InvalidConfiguration::mustBeDuration($key, $value);
    }

    /**
     * @param  list<string>  $allowed
     * @param  list<string>  $default
     * @return list<string>
     *
     * @throws InvalidConfiguration
     */
    protected function subsetOf(string $key, array $allowed, array $default): array
    {
        $value = $this->get($key, $default);

        if (! is_array($value) || $value === []) {
            throw InvalidConfiguration::mustBeSubsetOf($key, $allowed, $value);
        }

        foreach ($value as $item) {
            if (! in_array($item, $allowed, true)) {
                throw InvalidConfiguration::mustBeSubsetOf($key, $allowed, $item);
            }
        }

        return array_values(array_intersect($allowed, $value));
    }

    /** @throws InvalidConfiguration */
    protected function requiredDuration(string $key): Duration
    {
        $value = $this->get($key);

        if (! is_string($value)) {
            throw InvalidConfiguration::mustBeDuration($key, $value, nullable: false);
        }

        return Duration::tryParse($value) ?? throw InvalidConfiguration::mustBeDuration($key, $value, nullable: false);
    }

    /** @throws InvalidConfiguration */
    protected function positiveIntegerOrNull(string $key): ?int
    {
        $value = $this->get($key);

        if ($value === null) {
            return null;
        }

        $integer = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);

        if ($integer === false) {
            throw InvalidConfiguration::mustBePositiveIntegerOrNull($key, $value);
        }

        return $integer;
    }

    /** @throws InvalidConfiguration */
    protected function positiveInteger(string $key): int
    {
        $value = $this->get($key);
        $integer = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);

        if ($integer === false) {
            throw InvalidConfiguration::mustBePositiveInteger($key, $value);
        }

        return $integer;
    }
}
