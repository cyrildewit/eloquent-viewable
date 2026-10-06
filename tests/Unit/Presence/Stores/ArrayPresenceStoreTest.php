<?php

declare(strict_types=1);

use Carbon\Carbon;
use CyrildeWit\EloquentViewable\Presence\Data\Reference;
use CyrildeWit\EloquentViewable\Presence\Data\Scope;
use CyrildeWit\EloquentViewable\Presence\Data\Sighting;
use CyrildeWit\EloquentViewable\Presence\Stores\ArrayPresenceStore;
use CyrildeWit\EloquentViewable\Presence\Stores\NullPresenceStore;

function presenceSighting(string $visitor, int $key = 7, ?string $collection = null, ?Reference $viewer = null, string $type = 'post'): Sighting
{
    return new Sighting($type, $key, $visitor, Carbon::now(), $collection, $viewer);
}

/** @param  list<Reference>  $references */
function encodedReferences(array $references): array
{
    return array_map(static fn (Reference $reference): string => $reference->encode(), $references);
}

beforeEach(function (): void {
    Carbon::setTestNow('2026-10-06 12:00:00');
});

it('counts the distinct visitors seen since a moment', function (): void {
    $store = new ArrayPresenceStore;

    $store->touch(presenceSighting('one'));
    $store->touch(presenceSighting('one'));
    $store->touch(presenceSighting('two', collection: 'amp'));

    Carbon::setTestNow('2026-10-06 12:03:00');

    $store->touch(presenceSighting('three', key: 8));

    $since = Carbon::parse('2026-10-06 11:59:00');

    expect($store->countVisitors([new Scope, new Scope('post'), new Scope('post', 7), new Scope('post', 7, 'amp'), new Scope('post', 9)], $since))
        ->toBe([3, 3, 2, 1, 0])
        ->and($store->countVisitors([new Scope('post', 7)], Carbon::parse('2026-10-06 12:01:00')))->toBe([0]);
});

it('stops counting a visitor who leaves the viewable', function (): void {
    $store = new ArrayPresenceStore;
    $viewer = new Reference('user', 3);

    $store->touch(presenceSighting('one', collection: 'amp', viewer: $viewer));
    $store->leave(presenceSighting('one', collection: 'amp', viewer: $viewer));

    $since = Carbon::now()->subMinute();

    expect($store->countVisitors([new Scope, new Scope('post', 7), new Scope('post', 7, 'amp')], $since))->toBe([1, 0, 0])
        ->and($store->viewers(new Scope('post', 7), $since, 10))->toBeEmpty()
        ->and(encodedReferences($store->viewers(new Scope, $since, 10)))->toBe(['user|3']);
});

it('lists the viewables seen, the most recent first', function (): void {
    $store = new ArrayPresenceStore;

    $store->touch(presenceSighting('one', key: 1));
    Carbon::setTestNow('2026-10-06 12:01:00');
    $store->touch(presenceSighting('one', key: 2, type: 'video'));
    Carbon::setTestNow('2026-10-06 12:02:00');
    $store->touch(presenceSighting('one', key: 3));

    $since = Carbon::parse('2026-10-06 11:00:00');

    expect(encodedReferences($store->active(null, $since, 10)))->toBe(['post|3', 'video|2', 'post|1'])
        ->and(encodedReferences($store->active('post', $since, 10)))->toBe(['post|3', 'post|1'])
        ->and(encodedReferences($store->active(null, $since, 1)))->toBe(['post|3'])
        ->and($store->active(null, Carbon::now(), 10))->toBeEmpty();
});

it('lists the viewers seen, the most recent first', function (): void {
    $store = new ArrayPresenceStore;

    $store->touch(presenceSighting('one', viewer: new Reference('user', 1)));
    Carbon::setTestNow('2026-10-06 12:01:00');
    $store->touch(presenceSighting('two', viewer: new Reference('user', 2)));
    $store->touch(presenceSighting('three'));

    expect(encodedReferences($store->viewers(new Scope('post', 7), Carbon::parse('2026-10-06 11:00:00'), 10)))->toBe(['user|2', 'user|1']);
});

it('keeps nothing in the null store', function (): void {
    $store = new NullPresenceStore;

    $store->touch(presenceSighting('one'));
    $store->leave(presenceSighting('one'));

    expect($store->countVisitors([new Scope, new Scope('post')], Carbon::now()->subHour()))->toBe([0, 0])
        ->and($store->active(null, Carbon::now()->subHour(), 10))->toBeEmpty()
        ->and($store->viewers(new Scope, Carbon::now()->subHour(), 10))->toBeEmpty();
});
