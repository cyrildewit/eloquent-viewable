<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Jobs;

use CyrildeWit\EloquentViewable\Contracts\CreateView;
use CyrildeWit\EloquentViewable\PendingView;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;

final class StoreView implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;

    public function __construct(public PendingView $pending) {}

    public function handle(CreateView $action): void
    {
        $action->handle($this->pending);
    }
}
