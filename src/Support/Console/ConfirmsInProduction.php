<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Support\Console;

use Illuminate\Console\Command;

/**
 * Asks before a destructive command runs in production, unless `--force` is
 * passed. It asks through the console components rather than Laravel's
 * `ConfirmableTrait`, which asks through Laravel Prompts from Laravel 13.35.
 * Prompts only falls back to a question a test can answer while the
 * environment is `testing`, so a test that switches to `production` to reach
 * the question would never see it.
 *
 * @internal
 *
 * @phpstan-require-extends Command
 */
trait ConfirmsInProduction
{
    protected function confirmInProduction(): bool
    {
        if (! $this->getLaravel()->isProduction()) {
            return true;
        }

        if ($this->option('force') === true) {
            return true;
        }

        $this->components->alert('Application In Production');

        if ($this->components->confirm('Are you sure you want to run this command?')) {
            return true;
        }

        $this->components->warn('Command cancelled.');

        return false;
    }
}
