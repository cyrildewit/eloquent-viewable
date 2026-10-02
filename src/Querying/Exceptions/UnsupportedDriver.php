<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Querying\Exceptions;

use CyrildeWit\EloquentViewable\Exceptions\EloquentViewableException;
use CyrildeWit\EloquentViewable\Querying\Grammars\GrammarRegistry;
use Exception;

final class UnsupportedDriver extends Exception implements EloquentViewableException
{
    public static function noBucketGrammar(string $driver): self
    {
        return new self("No bucket grammar is registered for the `{$driver}` database driver. Register one through `".GrammarRegistry::class.'::register()`.');
    }
}
