<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Exceptions;

use Symfony\Component\Console\Exception\InvalidOptionException;

/** @internal */
final class InvalidOption extends InvalidOptionException implements EloquentViewableException
{
    public static function mustBePositiveInteger(string $name): self
    {
        return new self("The --{$name} option must be a positive integer.");
    }

    public static function mustBeDuration(string $name): self
    {
        return new self("The --{$name} option must be a duration such as `30d` or `2y`.");
    }

    /** @param  list<string>  $allowed */
    public static function mustBeOneOf(string $name, array $allowed): self
    {
        return new self("The --{$name} option must be one of `".implode('`, `', $allowed).'`.');
    }

    public static function mustBeDate(string $name, string $example): self
    {
        return new self("The --{$name} option must be a date such as `{$example}`.");
    }
}
