<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Recording\Jobs;

use CyrildeWit\EloquentViewable\Recording\Buffering\Flusher;
use CyrildeWit\EloquentViewable\Recording\Exceptions\StoreIsNotBuffered;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;

final class FlushBufferedViewsJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;

    public function __construct(public int $batch = 1000) {}

    /** @throws StoreIsNotBuffered */
    public function handle(Flusher $flusher): void
    {
        $flusher->flush($this->batch);
    }
}
