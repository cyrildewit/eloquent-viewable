<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Erasure\Console;

use Illuminate\Console\Command;
use Illuminate\Console\ConfirmableTrait;

/** @internal */
abstract class ErasureCommand extends Command
{
    use ConfirmableTrait;

    private const int Chunk = 1000;

    /**
     * It returns null once the error is reported.
     */
    protected function chunk(): ?int
    {
        $option = $this->option('chunk');

        if ($option === null) {
            return self::Chunk;
        }

        $chunk = filter_var($option, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);

        if ($chunk === false) {
            $this->components->error('The --chunk option must be a positive integer.');

            return null;
        }

        return $chunk;
    }
}
