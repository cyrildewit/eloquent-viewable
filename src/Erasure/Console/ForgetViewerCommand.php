<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Erasure\Console;

use CyrildeWit\EloquentViewable\Erasure\Actions\ForgetViewHistory;
use CyrildeWit\EloquentViewable\Erasure\Subject;
use Illuminate\Database\Eloquent\Model;

final class ForgetViewerCommand extends ErasureCommand
{
    #[\Override]
    protected $signature = 'views:forget-viewer
        {type : The model class or morph alias of the viewer, such as "App\Models\User"}
        {id : The key of the viewer, which may already be deleted}
        {--with-visitors : Also delete the guest views of the browsers the viewer was signed in on}
        {--chunk= : How many views to delete per statement}
        {--force : Delete without asking in production}';

    #[\Override]
    protected $description = 'Delete every view of one viewer';

    public function handle(ForgetViewHistory $forget): int
    {
        $chunk = $this->chunk();

        if ($chunk === null) {
            return self::FAILURE;
        }

        if (! $this->confirmToProceed()) {
            return self::FAILURE;
        }

        $type = $this->morphType($this->stringArgument('type'));
        $id = $this->stringArgument('id');

        $this->comment("Forgetting the views of {$type} {$id}...");

        $views = $forget->handle(Subject::viewerKey($type, $id), (bool) $this->option('with-visitors'), $chunk);

        $this->components->info("Deleted {$views} views of {$type} {$id}.");

        return self::SUCCESS;
    }

    /**
     * A class is stored as its morph alias. Anything else is taken to be an
     * alias already, as for a class that no longer exists.
     */
    private function morphType(string $type): string
    {
        if (is_subclass_of($type, Model::class)) {
            return (new $type)->getMorphClass();
        }

        return $type;
    }

    private function stringArgument(string $name): string
    {
        $value = $this->argument($name);

        return is_string($value) ? $value : '';
    }
}
