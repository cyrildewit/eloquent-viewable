<?php

declare(strict_types=1);

use CyrildeWit\EloquentViewable\Recording\Buffering\Flusher;
use CyrildeWit\EloquentViewable\Recording\Contracts\BufferedViewStore;
use CyrildeWit\EloquentViewable\Recording\Exceptions\StoreIsNotBuffered;
use CyrildeWit\EloquentViewable\Recording\Stores\NullStore;

it('flushes batch after batch until the buffer is empty', function (): void {
    $store = Mockery::mock(BufferedViewStore::class);
    $store->expects('flush')->with(500)->times(3)->andReturn(500, 120, 0);

    expect(new Flusher($store)->flush(500))->toBe(620);
});

it('flushes nothing from an empty buffer', function (): void {
    $store = Mockery::mock(BufferedViewStore::class);
    $store->expects('flush')->with(1000)->once()->andReturn(0);

    expect(new Flusher($store)->flush())->toBe(0);
});

it('refuses a store that does not buffer', function (): void {
    expect(fn (): int => new Flusher(new NullStore)->flush())
        ->toThrow(StoreIsNotBuffered::class, 'The configured view store `'.NullStore::class.'` writes views straight to their table, so there is nothing to flush.');
});
