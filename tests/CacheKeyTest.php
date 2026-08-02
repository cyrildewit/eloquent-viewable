<?php

declare(strict_types=1);

use CyrildeWit\EloquentViewable\CacheKey;
use CyrildeWit\EloquentViewable\Support\Period;
use CyrildeWit\EloquentViewable\Tests\TestClasses\Models\Apartment;
use CyrildeWit\EloquentViewable\Tests\TestClasses\Models\Post;

function cacheKey(object $viewable): CacheKey
{
    return new CacheKey($viewable, 'test-namespace');
}

beforeEach(function (): void {
    $this->firstPost = Post::factory()->create();
    $this->secondPost = Post::factory()->create();
});

it('is deterministic for identical inputs', function (): void {
    expect(cacheKey($this->firstPost)->make(Period::pastDays(2), true, 'reads'))
        ->toBe(cacheKey($this->firstPost)->make(Period::pastDays(2), true, 'reads'));
});

it('prefixes the key with a readable head', function (): void {
    $morphClass = $this->firstPost->getMorphClass();

    expect(cacheKey($this->firstPost)->make())
        ->toStartWith("test-namespace:{$morphClass}:{$this->firstPost->getKey()}:");
});

it('labels a viewable type without a key in the head', function (): void {
    $post = new Post;

    expect(cacheKey($post)->make())
        ->toStartWith('test-namespace:type:'.$post->getMorphClass().':');
});

it('produces a distinct key per model instance', function (): void {
    expect(cacheKey($this->firstPost)->make())
        ->not->toBe(cacheKey($this->secondPost)->make());
});

it('never collides across different viewable types', function (): void {
    $apartment = Apartment::factory()->create();

    expect(cacheKey($this->firstPost)->make())
        ->not->toBe(cacheKey($apartment)->make());
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
    expect((new CacheKey($this->firstPost, 'one'))->make())
        ->not->toBe((new CacheKey($this->firstPost, 'two'))->make());
});
