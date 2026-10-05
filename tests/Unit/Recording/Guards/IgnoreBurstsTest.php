<?php

declare(strict_types=1);

use Carbon\Carbon;
use CyrildeWit\EloquentViewable\Recording\Data\ViewAttempt;
use CyrildeWit\EloquentViewable\Recording\Events\BurstDetected;
use CyrildeWit\EloquentViewable\Recording\Guards\IgnoreBursts;
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
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Encryption\Encrypter;

beforeEach(function (): void {
    Carbon::setTestNow('2026-01-01 12:00:00.000');

    $this->events = Mockery::spy(Dispatcher::class);
});

afterEach(function (): void {
    Carbon::setTestNow();
});

/** @param  array<string, mixed>  $values */
function burstGuard(Dispatcher $events, array $values = []): IgnoreBursts
{
    $config = new Config(new Repository(['eloquent-viewable' => array_replace_recursive([
        'recording' => ['bursts' => ['max' => 2, 'seconds' => 2, 'block_for' => 60, 'by' => ['visitor'], 'store' => 'bursts', 'key' => 'bursts']],
        'visitor' => ['fingerprint' => ['store' => 'bursts', 'key' => 'salt']],
    ], $values)]));

    $cache = new CacheRepository(new ArrayStore);
    $factory = Mockery::mock(CacheFactory::class);
    $factory->allows('store')->with('bursts')->andReturn($cache);

    $fingerprint = new Fingerprint($config, $factory);

    return new IgnoreBursts(
        $config,
        new VisitorIdentity($config, new Encrypter(str_repeat('a', 32), 'AES-256-CBC'), $fingerprint),
        $fingerprint,
        $events,
        $factory,
    );
}

function burstAttempt(int $post, string $visitorId = 'visitor', string $ip = '203.0.113.7', ?Apartment $viewer = null): ViewAttempt
{
    $visitor = Mockery::mock(Visitor::class);
    $visitor->allows('id')->andReturn($visitorId);
    $visitor->allows('ip')->andReturn($ip);
    $visitor->allows('userAgent')->andReturn('Mozilla/5.0');

    return new ViewAttempt(new Post(['id' => $post]), $visitor, viewer: $viewer);
}

it('refuses a visitor that opens more different viewables than the maximum within the window', function (): void {
    $guard = burstGuard($this->events);

    expect($guard->allows(burstAttempt(1)))->toBeTrue()
        ->and($guard->allows(burstAttempt(2)))->toBeTrue()
        ->and($guard->allows(burstAttempt(3)))->toBeFalse()
        ->and($guard->allows(burstAttempt(1, 'another visitor')))->toBeTrue();
});

it('does not count the same viewable twice within the window', function (): void {
    $guard = burstGuard($this->events);

    expect($guard->allows(burstAttempt(1)))->toBeTrue()
        ->and($guard->allows(burstAttempt(1)))->toBeTrue()
        ->and($guard->allows(burstAttempt(1)))->toBeTrue()
        ->and($guard->allows(burstAttempt(2)))->toBeTrue();
});

it('blocks every view of the visitor until the block ends', function (): void {
    $guard = burstGuard($this->events);

    $guard->allows(burstAttempt(1));
    $guard->allows(burstAttempt(2));
    $guard->allows(burstAttempt(3));

    Carbon::setTestNow(Carbon::now()->addSeconds(30));

    expect($guard->allows(burstAttempt(1)))->toBeFalse();

    Carbon::setTestNow(Carbon::now()->addSeconds(31));

    expect($guard->allows(burstAttempt(1)))->toBeTrue();
});

it('counts the share of the previous window that still overlaps', function (): void {
    $guard = burstGuard($this->events);

    Carbon::setTestNow('2026-01-01 12:00:01.000');

    $guard->allows(burstAttempt(1));
    $guard->allows(burstAttempt(2));

    // Three quarters into the next window, a quarter of the previous one
    // still overlaps: 1 + 2 × 0.25 is 1.5, then 2 + 2 × 0.25 is 2.5.
    Carbon::setTestNow('2026-01-01 12:00:03.500');

    expect($guard->allows(burstAttempt(3)))->toBeTrue()
        ->and($guard->allows(burstAttempt(4)))->toBeFalse();
});

it('forgets a window once the next one has passed', function (): void {
    $guard = burstGuard($this->events);

    $guard->allows(burstAttempt(1));
    $guard->allows(burstAttempt(2));

    Carbon::setTestNow('2026-01-01 12:00:04.000');

    expect($guard->allows(burstAttempt(3)))->toBeTrue()
        ->and($guard->allows(burstAttempt(4)))->toBeTrue();
});

it('catches a visitor with a new cookie on every request by their network', function (): void {
    $byVisitor = burstGuard($this->events);
    $byNetwork = burstGuard($this->events, ['recording' => ['bursts' => ['by' => ['visitor', 'network']]]]);

    foreach ([1, 2, 3] as $post) {
        expect($byVisitor->allows(burstAttempt($post, "cookie {$post}")))->toBeTrue();
    }

    expect($byNetwork->allows(burstAttempt(1, 'cookie 1')))->toBeTrue()
        ->and($byNetwork->allows(burstAttempt(2, 'cookie 2')))->toBeTrue()
        ->and($byNetwork->allows(burstAttempt(3, 'cookie 3')))->toBeFalse()
        ->and($byNetwork->allows(burstAttempt(4, 'cookie 4', '198.51.100.7')))->toBeTrue();
});

it('dispatches BurstDetected once when the block starts', function (): void {
    $guard = burstGuard($this->events, ['recording' => ['bursts' => ['by' => ['network']]]]);

    $guard->allows(burstAttempt(1));
    $guard->allows(burstAttempt(2));
    $attempt = burstAttempt(3);
    $guard->allows($attempt);
    $guard->allows(burstAttempt(4));

    $this->events->shouldHaveReceived('dispatch')->once()->withArgs(
        fn (BurstDetected $event): bool => $event->attempt === $attempt && $event->by === 'network',
    );
});

it('counts once when the visitor id is the network fingerprint', function (): void {
    $guard = burstGuard($this->events, [
        'recording' => ['bursts' => ['by' => ['visitor', 'network']]],
        'visitor' => ['identity' => 'fingerprint'],
    ]);

    $guard->allows(burstAttempt(1));
    $guard->allows(burstAttempt(2));
    $guard->allows(burstAttempt(3));

    $this->events->shouldHaveReceived('dispatch')->once()->withArgs(
        fn (BurstDetected $event): bool => $event->by === 'visitor',
    );
});

it('keys the count on the viewer when the identity is the viewer', function (): void {
    $guard = burstGuard($this->events, ['visitor' => ['identity' => 'viewer']]);
    $viewer = new Apartment(['id' => 3]);

    expect($guard->allows(burstAttempt(1, 'a browser', viewer: $viewer)))->toBeTrue()
        ->and($guard->allows(burstAttempt(2, 'another browser', viewer: $viewer)))->toBeTrue()
        ->and($guard->allows(burstAttempt(3, 'a third browser', viewer: $viewer)))->toBeFalse();
});
