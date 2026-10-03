<?php

declare(strict_types=1);

use CyrildeWit\EloquentViewable\Recording\Exceptions\StoreIsNotBuffered;
use CyrildeWit\EloquentViewable\Recording\Jobs\FlushBufferedViewsJob;
use CyrildeWit\EloquentViewable\Recording\Stores\StoreManager;
use CyrildeWit\EloquentViewable\Tests\Fixtures\Models\Post;
use Illuminate\Contracts\Redis\Factory as RedisFactory;
use Illuminate\Support\Facades\Bus;

beforeEach(function (): void {
    $this->post = Post::factory()->create();
});

it('lands the buffered views from the queue', function (): void {
    config()->set('database.redis.client', 'predis');
    config()->set('eloquent-viewable.recording.store.driver', 'redis');
    $this->app->forgetInstance('redis');
    $this->app->make(StoreManager::class)->forgetDrivers();
    $this->app->make(RedisFactory::class)->connection()->command('del', ['eloquent-viewable:views']);

    views($this->post)->record();
    views($this->post)->record();

    Bus::dispatchSync(new FlushBufferedViewsJob(1));

    expect($this->post)->toHaveViewsCount(2);
});

it('fails when the configured store does not buffer', function (): void {
    expect(fn () => Bus::dispatchSync(new FlushBufferedViewsJob))->toThrow(StoreIsNotBuffered::class);
});
