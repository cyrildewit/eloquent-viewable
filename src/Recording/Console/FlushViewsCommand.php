<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Recording\Console;

use CyrildeWit\EloquentViewable\Recording\Buffering\Flusher;
use CyrildeWit\EloquentViewable\Recording\Exceptions\StoreIsNotBuffered;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

final class FlushViewsCommand extends Command
{
    #[\Override]
    protected $signature = 'views:flush {--batch=1000 : How many views to land per insert}';

    #[\Override]
    protected $description = 'Land the views the configured store has buffered in the views table';

    public function handle(Flusher $flusher): int
    {
        $batch = filter_var($this->option('batch'), FILTER_VALIDATE_INT);

        if ($batch === false || $batch < 1) {
            $this->components->error('The --batch option must be a positive integer.');

            return self::FAILURE;
        }

        try {
            $landed = $flusher->flush($batch);
        } catch (StoreIsNotBuffered $exception) {
            $this->components->error($exception->getMessage());

            return self::FAILURE;
        }

        $views = Str::plural('view', $landed);

        $this->components->info("Flushed {$landed} {$views}.");

        return self::SUCCESS;
    }
}
