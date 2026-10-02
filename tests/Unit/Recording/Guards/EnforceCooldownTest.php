<?php

declare(strict_types=1);

use Carbon\Carbon;
use CyrildeWit\EloquentViewable\Cooldowns\CooldownManager;
use CyrildeWit\EloquentViewable\Recording\Data\ViewAttempt;
use CyrildeWit\EloquentViewable\Recording\Guards\EnforceCooldown;
use CyrildeWit\EloquentViewable\Tests\Fixtures\Models\Post;
use CyrildeWit\EloquentViewable\Visitors\Contracts\Visitor;

it('allows an attempt without a cooldown and leaves the manager alone', function (): void {
    $cooldowns = Mockery::mock(CooldownManager::class);
    $cooldowns->shouldNotReceive('isActive', 'start');

    $attempt = new ViewAttempt(new Post(['id' => 1]), Mockery::mock(Visitor::class));
    $guard = new EnforceCooldown($cooldowns);

    expect($guard->allows($attempt))->toBeTrue();

    $guard->remember($attempt);
});

it('refuses while a cooldown is running without starting one', function (bool $active): void {
    $post = new Post(['id' => 1]);

    $cooldowns = Mockery::mock(CooldownManager::class);
    $cooldowns->expects('isActive')->with($post, 'custom')->andReturn($active);
    $cooldowns->shouldNotReceive('start');

    $attempt = new ViewAttempt($post, Mockery::mock(Visitor::class), 'custom', Carbon::now()->addMinutes(10));

    expect(new EnforceCooldown($cooldowns)->allows($attempt))->toBe(! $active);
})->with([
    'running' => [true],
    'none running' => [false],
]);

it('starts the cooldown once the view is remembered', function (): void {
    $post = new Post(['id' => 1]);
    $expiresAt = Carbon::now()->addMinutes(10);

    $cooldowns = Mockery::mock(CooldownManager::class);
    $cooldowns->expects('start')->with($post, $expiresAt, 'custom');

    new EnforceCooldown($cooldowns)->remember(new ViewAttempt($post, Mockery::mock(Visitor::class), 'custom', $expiresAt));
});
