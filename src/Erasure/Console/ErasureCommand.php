<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Erasure\Console;

use CyrildeWit\EloquentViewable\Exceptions\InvalidOption;
use CyrildeWit\EloquentViewable\Support\Console\ConfirmsInProduction;
use CyrildeWit\EloquentViewable\Support\Console\ReadsOptions;
use Illuminate\Console\Command;

/** @internal */
abstract class ErasureCommand extends Command
{
    use ConfirmsInProduction;
    use ReadsOptions;

    private const int Chunk = 1000;

    /** @throws InvalidOption */
    protected function chunk(): int
    {
        return $this->positiveInteger('chunk', self::Chunk);
    }

    protected function stringArgument(string $name): string
    {
        $value = $this->argument($name);

        return is_string($value) ? $value : '';
    }
}
