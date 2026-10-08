<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Erasure\Console;

use CyrildeWit\EloquentViewable\Erasure\Actions\ForgetViewHistory;
use CyrildeWit\EloquentViewable\Erasure\Subject;

final class ForgetVisitorCommand extends ErasureCommand
{
    #[\Override]
    protected $signature = 'views:forget-visitor
        {visitor : The visitor id, the value of the visitor cookie}
        {--chunk= : How many views to delete per statement}
        {--force : Delete without asking in production}';

    #[\Override]
    protected $description = 'Delete every view of one visitor';

    public function handle(ForgetViewHistory $forget): int
    {
        $chunk = $this->chunk();

        if ($chunk === null) {
            return self::FAILURE;
        }

        if (! $this->confirmInProduction()) {
            return self::FAILURE;
        }

        $visitor = $this->argument('visitor');
        $visitor = is_string($visitor) ? $visitor : '';

        $this->comment('Forgetting the views of the visitor...');

        $views = $forget->handle(Subject::visitor($visitor), chunk: $chunk);

        $this->components->info("Deleted {$views} views of the visitor.");

        return self::SUCCESS;
    }
}
