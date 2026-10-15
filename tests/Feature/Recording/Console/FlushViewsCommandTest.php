<?php

declare(strict_types=1);

use CyrildeWit\EloquentViewable\Models\View;
use CyrildeWit\EloquentViewable\Recording\Stores\StoreManager;
use CyrildeWit\EloquentViewable\Tests\Fixtures\Models\Post;
use Illuminate\Contracts\Redis\Factory as RedisFactory;
use Illuminate\Support\Facades\Artisan;

function usePredisStore(): void
{
    config()->set('database.redis.client', 'predis');
    config()->set('eloquent-viewable.recording.store.driver', 'redis');

    app()->forgetInstance('redis');
    app()->make(StoreManager::class)->forgetDrivers();

    app()->make(RedisFactory::class)->connection()->command('del', ['eloquent-viewable:views']);
}

beforeEach(function (): void {
    $this->post = Post::factory()->create();
});

it('is registered', function (): void {
    expect(Artisan::all())->toHaveKey('views:flush');
});

it('lands the buffered views and reports how many', function (): void {
    usePredisStore();

    views($this->post)->record();
    views($this->post)->record();

    $this->artisan('views:flush')
        ->expectsOutputToContain('Flushed 2 views.')
        ->assertSuccessful();

    expect($this->post)->toHaveViewsCount(2);
});

it('reports a single view in the singular', function (): void {
    usePredisStore();

    views($this->post)->record();

    $this->artisan('views:flush')
        ->expectsOutputToContain('Flushed 1 view.')
        ->assertSuccessful();
});

it('lands in batches of the given size', function (): void {
    usePredisStore();

    for ($i = 0; $i < 3; $i++) {
        views($this->post)->record();
    }

    $this->artisan('views:flush', ['--batch' => '2'])
        ->expectsOutputToContain('Flushed 3 views.')
        ->assertSuccessful();

    expect(View::count())->toBe(3);
});

it('rejects a batch size that is not a positive integer', function (string $batch): void {
    $this->artisan('views:flush', ['--batch' => $batch])
        ->expectsOutputToContain('The --batch option must be a positive integer.')
        ->assertFailed();
})->with(['0', '-5', 'many']);

it('fails when the configured store does not buffer', function (): void {
    $this->artisan('views:flush')
        ->expectsOutputToContain('there is nothing to flush')
        ->assertFailed();
});
