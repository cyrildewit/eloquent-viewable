<?php

declare(strict_types=1);

use CyrildeWit\EloquentViewable\Cooldowns\Contracts\CooldownStore;
use CyrildeWit\EloquentViewable\Cooldowns\CooldownManager;
use CyrildeWit\EloquentViewable\Cooldowns\Stores\CacheStore;
use CyrildeWit\EloquentViewable\Cooldowns\Stores\SessionStore;
use CyrildeWit\EloquentViewable\Exceptions\InvalidConfiguration;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;

it('is a singleton', function (): void {
    expect($this->app->make(CooldownManager::class))->toBe($this->app->make(CooldownManager::class));
});

it('builds the session store by default', function (): void {
    expect($this->app->make(CooldownManager::class)->driver())->toBeInstanceOf(SessionStore::class);
});

it('resolves the CooldownStore contract through the manager', function (): void {
    $store = $this->app->make(CooldownStore::class);

    expect($store)->toBe($this->app->make(CooldownManager::class)->driver())
        ->and($this->app->make(CooldownStore::class))->toBe($store);
});

it('keeps session cooldowns under the configured key', function (): void {
    Config::set('eloquent-viewable.cooldown.key', 'my-cooldowns');

    $this->app->make(CooldownStore::class)->put('post-1', now()->addMinute());

    expect($this->app['session.store']->get('my-cooldowns'))->toHaveKey('post-1');
});

it('builds the cache store named in the config', function (): void {
    Config::set('eloquent-viewable.cooldown.store', 'cache');

    expect($this->app->make(CooldownStore::class))->toBeInstanceOf(CacheStore::class);
});

it('keeps cache cooldowns in the configured cache store, prefixed with the key', function (): void {
    Config::set('cache.stores.cooldowns', ['driver' => 'array']);
    Config::set('eloquent-viewable.cooldown.store', 'cache');
    Config::set('eloquent-viewable.cooldown.cache.store', 'cooldowns');
    Config::set('eloquent-viewable.cooldown.key', 'my-cooldowns');

    $this->app->make(CooldownStore::class)->put('post-1', now()->addMinute());

    expect(Cache::store('cooldowns')->has('my-cooldowns:post-1'))->toBeTrue()
        ->and(Cache::store()->has('my-cooldowns:post-1'))->toBeFalse();
});

it('accepts a custom driver', function (): void {
    $custom = Mockery::mock(CooldownStore::class);

    $this->app->make(CooldownManager::class)->extend('custom', fn (Application $app): CooldownStore => $custom);

    Config::set('eloquent-viewable.cooldown.store', 'custom');

    expect($this->app->make(CooldownStore::class))->toBe($custom);
});

it('rejects a driver that is not registered', function (): void {
    Config::set('eloquent-viewable.cooldown.store', 'memcached');

    expect(fn (): CooldownStore => $this->app->make(CooldownStore::class))
        ->toThrow(InvalidConfiguration::class, 'The `eloquent-viewable.cooldown.store` config value names a driver that is not registered, `memcached` given.');
});
