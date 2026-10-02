<?php

declare(strict_types=1);

use CyrildeWit\EloquentViewable\Contracts\Viewable;
use CyrildeWit\EloquentViewable\Data\ViewRecord;
use CyrildeWit\EloquentViewable\Exceptions\InvalidConfiguration;
use CyrildeWit\EloquentViewable\Models\View;
use CyrildeWit\EloquentViewable\Recording\Contracts\ViewStore;
use CyrildeWit\EloquentViewable\Recording\Stores\DatabaseStore;
use CyrildeWit\EloquentViewable\Recording\Stores\NullStore;
use CyrildeWit\EloquentViewable\Recording\Stores\StoreManager;
use CyrildeWit\EloquentViewable\Tests\Fixtures\Models\Post;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Facades\Config;

it('is a singleton', function (): void {
    expect($this->app->make(StoreManager::class))->toBe($this->app->make(StoreManager::class));
});

it('builds the database store by default', function (): void {
    expect($this->app->make(StoreManager::class)->driver())->toBeInstanceOf(DatabaseStore::class);
});

it('builds the store named in the config', function (): void {
    Config::set('eloquent-viewable.recording.store.driver', 'null');

    expect($this->app->make(StoreManager::class)->driver())->toBeInstanceOf(NullStore::class)
        ->and($this->app->make(ViewStore::class))->toBeInstanceOf(NullStore::class);
});

it('builds a named driver on request', function (): void {
    expect($this->app->make(StoreManager::class)->driver('null'))->toBeInstanceOf(NullStore::class);
});

it('resolves the ViewStore contract through the manager', function (): void {
    $store = $this->app->make(ViewStore::class);

    expect($store)->toBe($this->app->make(StoreManager::class)->driver())
        ->and($this->app->make(ViewStore::class))->toBe($store);
});

it('records nothing under the null driver', function (): void {
    Config::set('eloquent-viewable.recording.store.driver', 'null');

    $post = Post::factory()->create();

    expect(views($post)->record())->toBeTrue()
        ->and(View::count())->toBe(0);
});

it('accepts a custom driver', function (): void {
    $custom = new class implements ViewStore
    {
        public function store(ViewRecord $record): void {}

        public function storeMany(iterable $records): void {}

        public function forget(Viewable $viewable): void {}
    };

    $this->app->make(StoreManager::class)->extend('custom', fn (Application $app): ViewStore => $custom);

    Config::set('eloquent-viewable.recording.store.driver', 'custom');

    expect($this->app->make(ViewStore::class))->toBe($custom);
});

it('rejects a driver that is not registered', function (): void {
    Config::set('eloquent-viewable.recording.store.driver', 'clickhouse');

    expect(fn (): ViewStore => $this->app->make(ViewStore::class))
        ->toThrow(InvalidConfiguration::class, 'The `eloquent-viewable.recording.store.driver` config value names a driver that is not registered, `clickhouse` given.');
});
