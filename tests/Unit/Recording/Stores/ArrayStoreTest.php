<?php

declare(strict_types=1);

use Carbon\Carbon;
use CyrildeWit\EloquentViewable\Data\ViewRecord;
use CyrildeWit\EloquentViewable\Recording\Stores\ArrayStore;
use CyrildeWit\EloquentViewable\Tests\Fixtures\Models\Apartment;
use CyrildeWit\EloquentViewable\Tests\Fixtures\Models\Post;

function arrayRecord(int|string $viewableId, string $viewableType = Post::class): ViewRecord
{
    return new ViewRecord($viewableId, $viewableType, 'visitor_one', null, Carbon::now());
}

it('keeps records in the order they were stored', function (): void {
    $store = new ArrayStore;
    $first = arrayRecord(1);
    $second = arrayRecord(2);

    $store->store($first);
    $store->store($second);

    expect($store->records())->toBe([$first, $second]);
});

it('appends a batch after the records it already holds', function (): void {
    $store = new ArrayStore;
    $first = arrayRecord(1);
    $second = arrayRecord(2);
    $third = arrayRecord(3);

    $store->store($first);
    $store->storeMany((function () use ($second, $third): Generator {
        yield $second;
        yield $third;
    })());

    expect($store->records())->toBe([$first, $second, $third]);
});

it('forgets the records of one viewable', function (): void {
    $store = new ArrayStore;
    $store->store(arrayRecord(1));
    $store->store($kept = arrayRecord(2));
    $store->store($other = arrayRecord(1, Apartment::class));

    $store->forget(new Post(['id' => 1]));

    expect($store->records())->toBe([$kept, $other]);
});

it('forgets every record of a type for a viewable without a key', function (): void {
    $store = new ArrayStore;
    $store->store(arrayRecord(1));
    $store->store(arrayRecord(2));
    $store->store($other = arrayRecord(1, Apartment::class));

    $store->forget(new Post);

    expect($store->records())->toBe([$other]);
});
