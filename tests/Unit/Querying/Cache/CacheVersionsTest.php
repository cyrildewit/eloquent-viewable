<?php

declare(strict_types=1);

use CyrildeWit\EloquentViewable\Contracts\Viewable;
use CyrildeWit\EloquentViewable\Querying\Cache\CacheVersions;
use CyrildeWit\EloquentViewable\Support\Config;
use Illuminate\Cache\ArrayStore;
use Illuminate\Cache\Repository as CacheRepository;
use Illuminate\Config\Repository;

function versionedViewable(int|string|null $key, string $type = 'posts'): Viewable
{
    $viewable = Mockery::mock(Viewable::class);
    $viewable->allows('getKey')->andReturn($key);
    $viewable->allows('getMorphClass')->andReturn($type);

    return $viewable;
}

beforeEach(function (): void {
    $this->cache = new CacheRepository(new ArrayStore);
    $this->versions = new CacheVersions(
        $this->cache,
        new Config(new Repository(['eloquent-viewable' => ['querying' => ['cache' => ['key' => 'views']]]])),
    );

    // What the reader does: read the versions, start the missing ones, join them.
    $this->of = function (?Viewable $viewable): string {
        $keys = $this->versions->keys($viewable);

        return $this->versions->stamp($this->versions->resolve($keys, $this->cache->many($keys)), $keys);
    };

    $this->post = versionedViewable(1);
    $this->otherPost = versionedViewable(2);
    $this->posts = versionedViewable(null);
    $this->apartment = versionedViewable(1, 'apartments');
    $this->apartments = versionedViewable(null, 'apartments');

    $this->snapshot = fn (): array => [
        'post' => ($this->of)($this->post),
        'otherPost' => ($this->of)($this->otherPost),
        'posts' => ($this->of)($this->posts),
        'apartment' => ($this->of)($this->apartment),
        'apartments' => ($this->of)($this->apartments),
        'ranking' => ($this->of)(null),
    ];

    $this->changed = function (array $before): array {
        $after = ($this->snapshot)();

        return array_keys(array_filter($before, fn (string $version, string $name): bool => $version !== $after[$name], ARRAY_FILTER_USE_BOTH));
    };
});

it('keeps the versions until something is forgotten', function (): void {
    $before = ($this->snapshot)();

    expect(($this->changed)($before))->toBe([]);
});

it('gives every scope its own version', function (): void {
    expect(array_unique(($this->snapshot)()))->toHaveCount(6);
});

it('forgets a model, the total and rankings of its type and the rankings across types', function (): void {
    $before = ($this->snapshot)();

    $this->versions->forgetCache($this->post);

    expect(($this->changed)($before))->toBe(['post', 'posts', 'ranking']);
});

it('forgets every model of a type for a viewable without a key', function (): void {
    $before = ($this->snapshot)();

    $this->versions->forgetCache($this->posts);

    expect(($this->changed)($before))->toBe(['post', 'otherPost', 'posts', 'ranking']);
});

it('forgets everything on a flush', function (): void {
    $before = ($this->snapshot)();

    $this->versions->flushCache();

    expect(($this->changed)($before))->toBe(array_keys($before));
});

it('starts a fresh version when one was evicted', function (): void {
    $before = ($this->snapshot)();

    $this->cache->forget('views:version:model:posts:1');

    expect(($this->changed)($before))->toBe(['post']);
});

it('stamps a viewable alike from a read it shares with others', function (): void {
    $keys = array_values(array_unique([...$this->versions->keys($this->post), ...$this->versions->keys($this->otherPost)]));
    $versions = $this->versions->resolve($keys, $this->cache->many($keys));

    expect($this->versions->stamp($versions, $this->versions->keys($this->post)))->toBe(($this->of)($this->post))
        ->and($this->versions->stamp($versions, $this->versions->keys($this->otherPost)))->toBe(($this->of)($this->otherPost));
});
