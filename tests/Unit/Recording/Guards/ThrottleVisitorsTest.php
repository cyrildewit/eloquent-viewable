<?php

declare(strict_types=1);

use Carbon\Carbon;
use CyrildeWit\EloquentViewable\Recording\Data\ViewAttempt;
use CyrildeWit\EloquentViewable\Recording\Guards\ThrottleVisitors;
use CyrildeWit\EloquentViewable\Support\Config;
use CyrildeWit\EloquentViewable\Tests\Fixtures\Models\Apartment;
use CyrildeWit\EloquentViewable\Tests\Fixtures\Models\Post;
use CyrildeWit\EloquentViewable\Visitors\Contracts\Visitor;
use CyrildeWit\EloquentViewable\Visitors\Fingerprint;
use CyrildeWit\EloquentViewable\Visitors\VisitorIdentity;
use Illuminate\Cache\ArrayStore;
use Illuminate\Cache\Repository as CacheRepository;
use Illuminate\Config\Repository;
use Illuminate\Contracts\Cache\Factory as CacheFactory;
use Illuminate\Encryption\Encrypter;

/** @param  array<string, mixed>  $values */
function throttleGuard(CacheRepository $cache, array $values = []): ThrottleVisitors
{
    $config = new Config(new Repository(['eloquent-viewable' => array_replace_recursive([
        'recording' => ['throttle' => ['max_per_minute' => 2, 'store' => 'throttle', 'key' => 'throttle']],
    ], $values)]));

    $factory = Mockery::mock(CacheFactory::class);
    $factory->allows('store')->with('throttle')->andReturn($cache);

    return new ThrottleVisitors(
        $config,
        new VisitorIdentity($config, new Encrypter(str_repeat('a', 32), 'AES-256-CBC'), new Fingerprint($config, $factory)),
        $factory,
    );
}

function throttleAttempt(string $visitorId, ?Post $post = null): ViewAttempt
{
    $visitor = Mockery::mock(Visitor::class);
    $visitor->allows('id')->andReturn($visitorId);

    return new ViewAttempt($post ?? new Post(['id' => 1]), $visitor);
}

it('refuses a visitor once they recorded the maximum within a minute, across viewables', function (): void {
    Carbon::setTestNow('2026-01-01 12:00:00');
    $guard = throttleGuard(new CacheRepository(new ArrayStore));

    $first = throttleAttempt('visitor', new Post(['id' => 1]));
    $second = throttleAttempt('visitor', new Post(['id' => 2]));

    expect($guard->allows($first))->toBeTrue();
    $guard->remember($first);

    expect($guard->allows($second))->toBeTrue();
    $guard->remember($second);

    expect($guard->allows(throttleAttempt('visitor', new Post(['id' => 3]))))->toBeFalse()
        ->and($guard->allows(throttleAttempt('another visitor')))->toBeTrue();

    Carbon::setTestNow(Carbon::now()->addSeconds(61));

    expect($guard->allows($first))->toBeTrue();
});

it('counts only the views it is told were recorded', function (): void {
    $guard = throttleGuard(new CacheRepository(new ArrayStore));

    $attempt = throttleAttempt('visitor');

    expect($guard->allows($attempt))->toBeTrue()
        ->and($guard->allows($attempt))->toBeTrue()
        ->and($guard->allows($attempt))->toBeTrue();
});

it('keys the count on the viewer when the identity is the viewer', function (): void {
    $guard = throttleGuard(new CacheRepository(new ArrayStore), ['visitor' => ['identity' => 'viewer']]);

    $viewer = new Apartment(['id' => 3]);
    $first = new ViewAttempt(new Post(['id' => 1]), throttleAttempt('a browser')->visitor, viewer: $viewer);
    $second = new ViewAttempt(new Post(['id' => 1]), throttleAttempt('another browser')->visitor, viewer: $viewer);

    $guard->remember($first);
    $guard->remember($second);

    expect($guard->allows($first))->toBeFalse();
});
