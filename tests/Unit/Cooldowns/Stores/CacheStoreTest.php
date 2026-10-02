<?php

declare(strict_types=1);

use Carbon\Carbon;
use CyrildeWit\EloquentViewable\Cooldowns\Stores\CacheStore;
use Illuminate\Cache\ArrayStore;
use Illuminate\Cache\Repository;

beforeEach(function (): void {
    Carbon::setTestNow('2026-01-01 12:00:00');

    $this->cache = new Repository(new ArrayStore);
    $this->store = new CacheStore($this->cache, 'cooldowns');
});

it('has no cooldown that was never started', function (): void {
    expect($this->store->has('post-1'))->toBeFalse();
});

it('keeps a started cooldown under the prefixed key', function (): void {
    $this->store->put('post-1', Carbon::now()->addMinutes(10));

    expect($this->store->has('post-1'))->toBeTrue()
        ->and($this->store->has('post-2'))->toBeFalse()
        ->and($this->cache->has('cooldowns:post-1'))->toBeTrue();
});

it('ends a cooldown once its expiry is reached', function (): void {
    $this->store->put('post-1', Carbon::now()->addMinutes(10));

    Carbon::setTestNow(Carbon::now()->addMinutes(10)->subSecond());

    expect($this->store->has('post-1'))->toBeTrue();

    Carbon::setTestNow(Carbon::now()->addSecond());

    expect($this->store->has('post-1'))->toBeFalse();
});

it('starts no cooldown that has already expired', function (): void {
    $this->store->put('post-1', Carbon::now()->subMinute());

    expect($this->store->has('post-1'))->toBeFalse();
});
