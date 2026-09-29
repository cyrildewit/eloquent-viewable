<?php

declare(strict_types=1);

use CyrildeWit\EloquentViewable\CacheKey;
use CyrildeWit\EloquentViewable\Contracts\Viewable;
use CyrildeWit\EloquentViewable\Support\Period;
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

function cacheKey(Viewable $viewable): CacheKey
{
    return new CacheKey($viewable, 'test-namespace');
}

beforeEach(function (): void {
    $this->firstPost = viewableStub(key: 1);
    $this->secondPost = viewableStub(key: 2);
});

it('is deterministic for identical inputs', function (): void {
    expect(cacheKey($this->firstPost)->make(Period::pastDays(2), true, 'reads'))
        ->toBe(cacheKey($this->firstPost)->make(Period::pastDays(2), true, 'reads'));
});

it('prefixes the key with a readable head', function (): void {
    expect(cacheKey($this->firstPost)->make())
        ->toStartWith('test-namespace:App\Models\Post:1:');
});

it('labels a viewable type without a key in the head', function (): void {
    expect(cacheKey(viewableStub(key: null))->make())
        ->toStartWith('test-namespace:type:App\Models\Post:');
});

it('produces a distinct key per model instance', function (): void {
    expect(cacheKey($this->firstPost)->make())
        ->not->toBe(cacheKey($this->secondPost)->make());
});

it('never collides across different viewable types', function (): void {
    $apartment = viewableStub(key: 1, morphClass: 'App\Models\Apartment');

    expect(cacheKey($this->firstPost)->make())
        ->not->toBe(cacheKey($apartment)->make());
});

it('changes the key when the connection changes', function (): void {
    $default = cacheKey($this->firstPost)->make();

    expect(cacheKey(viewableStub(connection: 'analytics'))->make())
        ->not->toBe($default)
        ->and(cacheKey(viewableStub(database: 'tenant_two'))->make())->not->toBe($default);
});

it('changes the key when the period changes', function (): void {
    $default = cacheKey($this->firstPost)->make();

    expect(cacheKey($this->firstPost)->make(Period::since('2019-03-21')))
        ->not->toBe($default)
        ->and(cacheKey($this->firstPost)->make(Period::upto('2020-07-03')))->not->toBe($default)
        ->and(cacheKey($this->firstPost)->make(Period::pastDays(2)))->not->toBe($default);
});

it('distinguishes relative periods from one another', function (): void {
    expect(cacheKey($this->firstPost)->make(Period::pastDays(2)))
        ->not->toBe(cacheKey($this->firstPost)->make(Period::pastDays(3)))
        ->and(cacheKey($this->firstPost)->make(Period::subSeconds(34)))
        ->not->toBe(cacheKey($this->firstPost)->make(Period::subWeeks(3)));
});

it('changes the key when the unique flag changes', function (): void {
    expect(cacheKey($this->firstPost)->make(null, true))
        ->not->toBe(cacheKey($this->firstPost)->make(null, false));
});

it('changes the key when the collection changes', function (): void {
    $default = cacheKey($this->firstPost)->make();

    expect(cacheKey($this->firstPost)->make(null, false, 'some-collection'))
        ->not->toBe($default)
        ->and(cacheKey($this->firstPost)->make(null, false, 'other-collection'))
        ->not->toBe(cacheKey($this->firstPost)->make(null, false, 'some-collection'));
});

it('changes the key when the prefix changes', function (): void {
    expect(new CacheKey($this->firstPost, 'one')->make())
        ->not->toBe(new CacheKey($this->firstPost, 'two')->make());
});
