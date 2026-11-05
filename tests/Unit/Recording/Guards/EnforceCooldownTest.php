<?php

declare(strict_types=1);

use Carbon\Carbon;
use CyrildeWit\EloquentViewable\Cooldowns\Contracts\CooldownStore;
use CyrildeWit\EloquentViewable\Cooldowns\Cooldown;
use CyrildeWit\EloquentViewable\Recording\Data\ViewAttempt;
use CyrildeWit\EloquentViewable\Recording\Guards\EnforceCooldown;
use CyrildeWit\EloquentViewable\Support\Config;
use CyrildeWit\EloquentViewable\Tests\Fixtures\Models\Apartment;
use CyrildeWit\EloquentViewable\Tests\Fixtures\Models\Post;
use CyrildeWit\EloquentViewable\Visitors\Contracts\Visitor;
use CyrildeWit\EloquentViewable\Visitors\Fingerprint;
use CyrildeWit\EloquentViewable\Visitors\VisitorIdentity;
use Illuminate\Config\Repository;
use Illuminate\Contracts\Cache\Factory as CacheFactory;
use Illuminate\Encryption\Encrypter;

const COOLDOWN_KEY = 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa';

function cooldownVisitor(): Visitor
{
    $visitor = Mockery::mock(Visitor::class);
    $visitor->allows('id')->andReturn('visitor');

    return $visitor;
}

function cooldownGuard(CooldownStore $cooldowns, string $identity = 'cookie'): EnforceCooldown
{
    $config = new Config(new Repository(['eloquent-viewable' => ['visitor' => ['identity' => $identity]]]));

    return new EnforceCooldown($cooldowns, new VisitorIdentity($config, new Encrypter(COOLDOWN_KEY, 'AES-256-CBC'), new Fingerprint($config, Mockery::mock(CacheFactory::class))));
}

it('allows an attempt without a cooldown and leaves the store alone', function (): void {
    $cooldowns = Mockery::mock(CooldownStore::class);
    $cooldowns->shouldNotReceive('has', 'put');

    $attempt = new ViewAttempt(new Post(['id' => 1]), Mockery::mock(Visitor::class));
    $guard = cooldownGuard($cooldowns);

    expect($guard->allows($attempt))->toBeTrue();

    $guard->remember($attempt);
});

it('refuses while the visitor\'s cooldown is running without starting one', function (bool $running): void {
    $post = new Post(['id' => 1]);

    $cooldowns = Mockery::mock(CooldownStore::class);
    $cooldowns->expects('has')->with(Cooldown::of($post, 'visitor', 'custom')->key())->andReturn($running);
    $cooldowns->shouldNotReceive('put');

    $attempt = new ViewAttempt($post, cooldownVisitor(), 'custom', Carbon::now()->addMinutes(10));

    expect(cooldownGuard($cooldowns)->allows($attempt))->toBe(! $running);
})->with([
    'running' => [true],
    'none running' => [false],
]);

it('starts the visitor\'s cooldown once the view is remembered', function (): void {
    $post = new Post(['id' => 1]);
    $expiresAt = Carbon::now()->addMinutes(10);

    $cooldowns = Mockery::mock(CooldownStore::class);
    $cooldowns->expects('put')->with(Cooldown::of($post, 'visitor', 'custom')->key(), $expiresAt);

    cooldownGuard($cooldowns)->remember(new ViewAttempt($post, cooldownVisitor(), 'custom', $expiresAt));
});

it('keys the cooldown on the viewer when the identity is the viewer', function (): void {
    $post = new Post(['id' => 1]);
    $viewer = new Apartment(['id' => 3]);
    $expiresAt = Carbon::now()->addMinutes(10);
    $key = Cooldown::of($post, hash_hmac('sha256', Apartment::class.'|3', COOLDOWN_KEY), 'custom')->key();

    $cooldowns = Mockery::mock(CooldownStore::class);
    $cooldowns->expects('has')->with($key)->andReturn(false);
    $cooldowns->expects('put')->with($key, $expiresAt);

    $visitor = Mockery::mock(Visitor::class);
    $visitor->shouldNotReceive('id');

    $attempt = new ViewAttempt($post, $visitor, 'custom', $expiresAt, viewer: $viewer);
    $guard = cooldownGuard($cooldowns, 'viewer');

    expect($guard->allows($attempt))->toBeTrue();

    $guard->remember($attempt);
});
