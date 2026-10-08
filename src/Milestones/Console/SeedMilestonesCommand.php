<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Milestones\Console;

use CyrildeWit\EloquentViewable\Milestones\Actions\CheckMilestones;
use CyrildeWit\EloquentViewable\Support\Config;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Str;

final class SeedMilestonesCommand extends Command
{
    #[\Override]
    protected $signature = 'views:seed-milestones
        {model? : The model class or morph alias to seed, every configured one when left out}';

    #[\Override]
    protected $description = 'Mark every model at its current count, so the milestones it already passed never fire';

    public function handle(CheckMilestones $milestones, Config $config): int
    {
        $configured = $config->milestones();

        if ($configured === []) {
            $this->components->info('Nothing to seed, `milestones.thresholds` is empty.');

            return self::SUCCESS;
        }

        $only = $this->model();

        if ($only !== null && ! array_key_exists($only, $configured)) {
            $this->components->error("The `{$only}` model has no thresholds in `milestones.thresholds`.");

            return self::FAILURE;
        }

        foreach ($milestones->seed($only) as $class => $models) {
            $noun = Str::plural(Str::afterLast($class, '\\'), $models);

            $this->components->info("Marked {$models} {$noun} at their current count.");
        }

        return self::SUCCESS;
    }

    private function model(): ?string
    {
        $model = $this->argument('model');

        if (! is_string($model)) {
            return null;
        }

        return Relation::getMorphedModel($model) ?? $model;
    }
}
