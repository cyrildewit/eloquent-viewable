<?php

declare(strict_types=1);

use CyrildeWit\EloquentViewable\Contracts\Viewable;
use CyrildeWit\EloquentViewable\Querying\Cache\CacheKey;
use CyrildeWit\EloquentViewable\Support\Granularity;
use CyrildeWit\EloquentViewable\Support\Period;
use CyrildeWit\EloquentViewable\Support\Timezone;
use CyrildeWit\EloquentViewable\Support\ViewsQuery;
use CyrildeWit\EloquentViewable\Tests\Fixtures\Models\Apartment;
use CyrildeWit\EloquentViewable\Tests\Fixtures\Models\Post;
use Illuminate\Database\Connection;

/**
 * Build a viewable double exposing only what CacheKey reads from a model.
 */
function viewableStub(
    ?int $key = 1,
    string $morphClass = 'App\Models\Post',
    string $connection = 'testing',
    string $database = ':memory:',
): Viewable {
    $connectionMock = Mockery::mock(Connection::class);
    $connectionMock->allows('getName')->andReturn($connection);
    $connectionMock->allows('getDatabaseName')->andReturn($database);

    $viewable = Mockery::mock(Viewable::class);
    $viewable->allows('getKey')->andReturn($key);
    $viewable->allows('getMorphClass')->andReturn($morphClass);
    $viewable->allows('getConnection')->andReturn($connectionMock);

    return $viewable;
}

function cacheKey(Viewable $viewable, string $source = 'database'): CacheKey
{
    return new CacheKey($viewable, 'test-namespace', $source);
}

beforeEach(function (): void {
    $this->firstPost = viewableStub(key: 1);
    $this->secondPost = viewableStub(key: 2);
});

it('is deterministic for identical inputs', function (): void {
    expect(cacheKey($this->firstPost)->make(new ViewsQuery(Period::pastDays(2), 'reads', true)))
        ->toBe(cacheKey($this->firstPost)->make(new ViewsQuery(Period::pastDays(2), 'reads', true)));
});

it('prefixes the key with a readable head', function (): void {
    expect(cacheKey($this->firstPost)->make(new ViewsQuery))
        ->toStartWith('test-namespace:App\Models\Post:1:');
});

it('labels a viewable type without a key in the head', function (): void {
    expect(cacheKey(viewableStub(key: null))->make(new ViewsQuery))
        ->toStartWith('test-namespace:type:App\Models\Post:');
});

it('produces a distinct key per model instance', function (): void {
    expect(cacheKey($this->firstPost)->make(new ViewsQuery))
        ->not->toBe(cacheKey($this->secondPost)->make(new ViewsQuery));
});

it('never collides across different viewable types', function (): void {
    $apartment = viewableStub(key: 1, morphClass: 'App\Models\Apartment');

    expect(cacheKey($this->firstPost)->make(new ViewsQuery))
        ->not->toBe(cacheKey($apartment)->make(new ViewsQuery));
});

it('changes the key when the connection changes', function (): void {
    $default = cacheKey($this->firstPost)->make(new ViewsQuery);

    expect(cacheKey(viewableStub(connection: 'analytics'))->make(new ViewsQuery))
        ->not->toBe($default)
        ->and(cacheKey(viewableStub(database: 'tenant_two'))->make(new ViewsQuery))->not->toBe($default);
});

it('changes the key when the period changes', function (): void {
    $default = cacheKey($this->firstPost)->make(new ViewsQuery);

    expect(cacheKey($this->firstPost)->make(new ViewsQuery(Period::since('2019-03-21'))))
        ->not->toBe($default)
        ->and(cacheKey($this->firstPost)->make(new ViewsQuery(Period::upto('2020-07-03'))))->not->toBe($default)
        ->and(cacheKey($this->firstPost)->make(new ViewsQuery(Period::pastDays(2))))->not->toBe($default);
});

it('distinguishes relative periods from one another', function (): void {
    expect(cacheKey($this->firstPost)->make(new ViewsQuery(Period::pastDays(2))))
        ->not->toBe(cacheKey($this->firstPost)->make(new ViewsQuery(Period::pastDays(3))))
        ->and(cacheKey($this->firstPost)->make(new ViewsQuery(Period::subSeconds(34))))
        ->not->toBe(cacheKey($this->firstPost)->make(new ViewsQuery(Period::subWeeks(3))));
});

it('changes the key when the unique flag changes', function (): void {
    expect(cacheKey($this->firstPost)->make(new ViewsQuery(unique: true)))
        ->not->toBe(cacheKey($this->firstPost)->make(new ViewsQuery(unique: false)));
});

it('changes the key when the collection changes', function (): void {
    $default = cacheKey($this->firstPost)->make(new ViewsQuery);

    expect(cacheKey($this->firstPost)->make(new ViewsQuery(collection: 'some-collection')))
        ->not->toBe($default)
        ->and(cacheKey($this->firstPost)->make(new ViewsQuery(collection: 'other-collection')))
        ->not->toBe(cacheKey($this->firstPost)->make(new ViewsQuery(collection: 'some-collection')));
});

it('changes the key when the prefix changes', function (): void {
    expect(new CacheKey($this->firstPost, 'one', 'database')->make(new ViewsQuery))
        ->not->toBe(new CacheKey($this->firstPost, 'two', 'database')->make(new ViewsQuery));
});

it('changes the key when the source driver changes', function (): void {
    expect(cacheKey($this->firstPost, source: 'database')->make(new ViewsQuery))
        ->not->toBe(cacheKey($this->firstPost, source: 'rollup')->make(new ViewsQuery))
        ->and(cacheKey($this->firstPost, source: 'rollup')->make(new ViewsQuery))
        ->toBe(cacheKey($this->firstPost, source: 'rollup')->make(new ViewsQuery));
});

it('changes the key when the granularity changes', function (): void {
    $default = cacheKey($this->firstPost)->make(new ViewsQuery(Period::pastDays(2)));

    expect(cacheKey($this->firstPost)->make(new ViewsQuery(Period::pastDays(2)), Granularity::Day))
        ->not->toBe($default)
        ->and(cacheKey($this->firstPost)->make(new ViewsQuery(Period::pastDays(2)), Granularity::Hour))
        ->not->toBe(cacheKey($this->firstPost)->make(new ViewsQuery(Period::pastDays(2)), Granularity::Day));
});

it('changes the key when the timezone changes', function (): void {
    $default = cacheKey($this->firstPost)->make(new ViewsQuery(Period::pastDays(2)), Granularity::Day);

    expect(cacheKey($this->firstPost)->make(new ViewsQuery(Period::pastDays(2), timezone: new Timezone('Australia/Sydney')), Granularity::Day))
        ->not->toBe($default)
        ->and(cacheKey($this->firstPost)->make(new ViewsQuery(Period::pastDays(2), timezone: new Timezone('Australia/Sydney')), Granularity::Day))
        ->not->toBe(cacheKey($this->firstPost)->make(new ViewsQuery(Period::pastDays(2), timezone: new Timezone('Europe/Amsterdam')), Granularity::Day));
});

it('changes the key when the viewer changes', function (): void {
    $default = cacheKey($this->firstPost)->make(new ViewsQuery);
    $userSeven = new Post(['id' => 7]);
    $userEight = new Post(['id' => 8]);
    $apartmentSeven = new Apartment(['id' => 7]);

    expect(cacheKey($this->firstPost)->make(new ViewsQuery(viewer: $userSeven)))
        ->not->toBe($default)
        ->and(cacheKey($this->firstPost)->make(new ViewsQuery(viewer: $userSeven)))
        ->toBe(cacheKey($this->firstPost)->make(new ViewsQuery(viewer: new Post(['id' => 7]))))
        ->and(cacheKey($this->firstPost)->make(new ViewsQuery(viewer: $userSeven)))
        ->not->toBe(cacheKey($this->firstPost)->make(new ViewsQuery(viewer: $userEight)))
        ->and(cacheKey($this->firstPost)->make(new ViewsQuery(viewer: $userSeven)))
        ->not->toBe(cacheKey($this->firstPost)->make(new ViewsQuery(viewer: $apartmentSeven)));
});

it('changes the key when a relative period is anchored in another timezone', function (): void {
    expect(cacheKey($this->firstPost)->make(new ViewsQuery(Period::pastDays(2, 'Australia/Sydney'))))
        ->not->toBe(cacheKey($this->firstPost)->make(new ViewsQuery(Period::pastDays(2))));
});
