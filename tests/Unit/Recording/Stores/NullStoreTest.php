<?php

declare(strict_types=1);

use Carbon\Carbon;
use CyrildeWit\EloquentViewable\Contracts\Viewable;
use CyrildeWit\EloquentViewable\Data\ViewRecord;
use CyrildeWit\EloquentViewable\Recording\Contracts\ViewStore;
use CyrildeWit\EloquentViewable\Recording\Stores\NullStore;

it('is a view store', function (): void {
    expect(new NullStore)->toBeInstanceOf(ViewStore::class);
});

it('discards records and forgets nothing', function (): void {
    $store = new NullStore;
    $record = new ViewRecord(
        viewableId: 1,
        viewableType: 'posts',
        visitor: 'visitor_one',
        collection: null,
        viewedAt: Carbon::now(),
    );

    $store->store($record);
    $store->storeMany([$record, $record]);

    $viewable = Mockery::mock(Viewable::class);
    $viewable->shouldNotReceive('getKey');
    $viewable->shouldNotReceive('getMorphClass');

    $store->forget($viewable);
});
