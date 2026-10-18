<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Recording\Console;

use CyrildeWit\EloquentViewable\Exceptions\InvalidOption;
use CyrildeWit\EloquentViewable\Recording\Buffering\Flusher;
use CyrildeWit\EloquentViewable\Recording\Exceptions\StoreIsNotBuffered;
use CyrildeWit\EloquentViewable\Support\Console\ReadsOptions;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

final class FlushViewsCommand extends Command
{
    use ReadsOptions;

    #[\Override]
    protected $signature = 'views:flush {--batch=1000 : How many views to land per insert}';

    #[\Override]
    protected $description = 'Land the views the configured store has buffered in the views table';

    /** @throws InvalidOption */
    public function handle(Flusher $flusher): int
    {
        $batch = $this->positiveInteger('batch', 1000);

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
