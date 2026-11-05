<?php

declare(strict_types=1);

use CyrildeWit\EloquentViewable\Recording\Buffering\Flusher;
use CyrildeWit\EloquentViewable\Recording\Contracts\BufferedViewStore;
use CyrildeWit\EloquentViewable\Recording\Exceptions\StoreIsNotBuffered;
use CyrildeWit\EloquentViewable\Recording\Jobs\FlushBufferedViewsJob;
use CyrildeWit\EloquentViewable\Recording\Stores\NullStore;
use Illuminate\Contracts\Queue\ShouldQueue;

it('is queueable', function (): void {
    expect(new FlushBufferedViewsJob)->toBeInstanceOf(ShouldQueue::class);
});

it('exposes the batch size so it is serialized with the job', function (): void {
    expect(new FlushBufferedViewsJob(250)->batch)->toBe(250)
        ->and(new FlushBufferedViewsJob()->batch)->toBe(1000);
});

it('flushes the buffer in batches of its size', function (): void {
    $store = Mockery::mock(BufferedViewStore::class);
    $store->expects('flush')->with(250)->twice()->andReturn(250, 0);

    new FlushBufferedViewsJob(250)->handle(new Flusher($store));
});

it('fails when the store does not buffer', function (): void {
    expect(fn () => new FlushBufferedViewsJob()->handle(new Flusher(new NullStore)))
        ->toThrow(StoreIsNotBuffered::class);
});
