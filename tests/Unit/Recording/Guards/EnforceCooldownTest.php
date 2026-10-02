<?php

declare(strict_types=1);

use Carbon\Carbon;
use CyrildeWit\EloquentViewable\Cooldowns\Contracts\CooldownStore;
use CyrildeWit\EloquentViewable\Cooldowns\Cooldown;
use CyrildeWit\EloquentViewable\Recording\Data\ViewAttempt;
use CyrildeWit\EloquentViewable\Recording\Guards\EnforceCooldown;
use CyrildeWit\EloquentViewable\Tests\Fixtures\Models\Post;
use CyrildeWit\EloquentViewable\Visitors\Contracts\Visitor;

function cooldownVisitor(): Visitor
{
    $visitor = Mockery::mock(Visitor::class);
    $visitor->allows('id')->andReturn('visitor');

    return $visitor;
}

it('allows an attempt without a cooldown and leaves the store alone', function (): void {
    $cooldowns = Mockery::mock(CooldownStore::class);
    $cooldowns->shouldNotReceive('has', 'put');

    $attempt = new ViewAttempt(new Post(['id' => 1]), Mockery::mock(Visitor::class));
    $guard = new EnforceCooldown($cooldowns);

    expect($guard->allows($attempt))->toBeTrue();

    $guard->remember($attempt);
});

it('refuses while the visitor\'s cooldown is running without starting one', function (bool $running): void {
    $post = new Post(['id' => 1]);

    $cooldowns = Mockery::mock(CooldownStore::class);
    $cooldowns->expects('has')->with(Cooldown::of($post, 'visitor', 'custom')->key())->andReturn($running);
    $cooldowns->shouldNotReceive('put');

    $attempt = new ViewAttempt($post, cooldownVisitor(), 'custom', Carbon::now()->addMinutes(10));

    expect(new EnforceCooldown($cooldowns)->allows($attempt))->toBe(! $running);
})->with([
    'running' => [true],
    'none running' => [false],
]);

it('starts the visitor\'s cooldown once the view is remembered', function (): void {
    $post = new Post(['id' => 1]);
    $expiresAt = Carbon::now()->addMinutes(10);

    $cooldowns = Mockery::mock(CooldownStore::class);
    $cooldowns->expects('put')->with(Cooldown::of($post, 'visitor', 'custom')->key(), $expiresAt);

    new EnforceCooldown($cooldowns)->remember(new ViewAttempt($post, cooldownVisitor(), 'custom', $expiresAt));
});
