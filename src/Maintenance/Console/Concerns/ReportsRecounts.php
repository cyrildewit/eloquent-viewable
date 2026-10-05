<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Maintenance\Console\Concerns;

use CyrildeWit\EloquentViewable\Maintenance\Data\RecountRun;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

/**
 * @internal
 *
 * @phpstan-require-extends Command
 */
trait ReportsRecounts
{
    protected function reportRecounted(RecountRun $run): void
    {
        foreach ($run->models as $class => $models) {
            $noun = Str::plural(Str::afterLast($class, '\\'), $models);

            $this->components->info("Recounted {$models} {$noun}.");
        }
    }
}
