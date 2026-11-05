<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Spikes\Console;

use CyrildeWit\EloquentViewable\Spikes\Actions\DetectSpikes;
use CyrildeWit\EloquentViewable\Support\Config;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

final class DetectSpikesCommand extends Command
{
    #[\Override]
    protected $signature = 'views:detect-spikes';

    #[\Override]
    protected $description = 'Compare the last closed window of the watched models with their past, and dispatch an event when one spikes, drops or settles';

    public function handle(DetectSpikes $spikes, Config $config): int
    {
        if ($config->spikes() === []) {
            $this->components->info('Nothing to watch, `spikes.types` is empty.');

            return self::SUCCESS;
        }

        foreach ($spikes->handle() as $run) {
            $noun = Str::plural(Str::afterLast($run->class, '\\'));

            $this->components->info("Watched {$noun}: {$run->spiked} spiked, {$run->dropped} dropped, {$run->settled} settled.");
        }

        return self::SUCCESS;
    }
}
