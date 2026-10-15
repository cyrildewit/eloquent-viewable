<?php

declare(strict_types=1);

use CyrildeWit\EloquentViewable\Debugging\Debugbar\RegisterViewsCollector;
use CyrildeWit\EloquentViewable\Debugging\Debugbar\ViewsCollector;
use CyrildeWit\EloquentViewable\Recording\Events\ViewAttempted;
use CyrildeWit\EloquentViewable\Tests\Fixtures\Guards\RefuseAll;
use CyrildeWit\EloquentViewable\Tests\Fixtures\Models\Post;
use Fruitcake\LaravelDebugbar\LaravelDebugbar;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;

/**
 * Debugbar refuses to collect in the console, so the instance says it is
 * collecting without booting its own collectors.
 */
function fakeDebugbar(Application $app, bool $collecting = true): LaravelDebugbar
{
    $debugbar = new class($app, $app->make('request')) extends LaravelDebugbar
    {
        public bool $collecting = true;

        #[Override]
        public function isCollecting(): bool
        {
            return $this->collecting;
        }
    };

    $debugbar->collecting = $collecting;

    $app->instance(LaravelDebugbar::class, $debugbar);

    return $debugbar;
}

/** @return list<string> */
function listedViews(LaravelDebugbar $debugbar): array
{
    $collector = $debugbar->getCollector(ViewsCollector::Name);

    assert($collector instanceof ViewsCollector);

    return array_column($collector->collect()['messages'], 'message');
}

beforeEach(function (): void {
    $this->post = Post::factory()->create();
});

it('lists the views stored and skipped during the request', function (): void {
    $debugbar = fakeDebugbar($this->app);

    $this->app->make(RegisterViewsCollector::class)();

    views($this->post)->record();

    Config::set('eloquent-viewable.recording.guards', [RefuseAll::class]);

    views($this->post)->record();

    $type = $this->post->getMorphClass();
    $key = $this->post->getKey();

    expect(listedViews($debugbar))->toBe([
        "{$type}({$key}) stored",
        "{$type}({$key}) skipped by RefuseAll",
    ]);
});

it('lists a queued view before a worker has stored it', function (): void {
    Queue::fake();

    $debugbar = fakeDebugbar($this->app);

    $this->app->make(RegisterViewsCollector::class)();

    views($this->post)->queue()->record();

    $type = $this->post->getMorphClass();
    $key = $this->post->getKey();

    expect(listedViews($debugbar))->toBe(["{$type}({$key}) queued"]);
});

it('adds nothing when Debugbar is not there', function (): void {
    $this->app->make(RegisterViewsCollector::class)();

    expect($this->app->resolved(LaravelDebugbar::class))
        ->toBeFalse()
        ->and(Event::hasListeners(ViewAttempted::class))
        ->toBeFalse();
});

it('adds nothing when Debugbar is not collecting', function (): void {
    $debugbar = fakeDebugbar($this->app, collecting: false);

    $this->app->make(RegisterViewsCollector::class)();

    expect($debugbar->hasCollector(ViewsCollector::Name))
        ->toBeFalse()
        ->and(Event::hasListeners(ViewAttempted::class))
        ->toBeFalse();
});

it('adds nothing when the collector is turned off in the Debugbar config', function (): void {
    Config::set('debugbar.collectors.eloquent_viewable', false);

    $debugbar = fakeDebugbar($this->app);

    $this->app->make(RegisterViewsCollector::class)();

    expect($debugbar->hasCollector(ViewsCollector::Name))
        ->toBeFalse()
        ->and(Event::hasListeners(ViewAttempted::class))
        ->toBeFalse();
});

it('adds the collector once', function (): void {
    $debugbar = fakeDebugbar($this->app);

    $this->app->make(RegisterViewsCollector::class)();
    $this->app->make(RegisterViewsCollector::class)();

    views($this->post)->record();

    expect(listedViews($debugbar))->toHaveCount(1);
});
