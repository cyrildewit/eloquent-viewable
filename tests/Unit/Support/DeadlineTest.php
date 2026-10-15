<?php

declare(strict_types=1);

use Carbon\Carbon;
use CyrildeWit\EloquentViewable\Support\Deadline;

afterEach(function (): void {
    Carbon::setTestNow();
});

it('never passes without a deadline', function (): void {
    Carbon::setTestNow('2026-01-01 00:00:00');
    $deadline = Deadline::none();

    Carbon::setTestNow('2099-01-01 00:00:00');

    expect($deadline->passed())->toBeFalse();
});

it('passes once its seconds are up', function (): void {
    Carbon::setTestNow('2026-01-01 00:00:00');
    $deadline = Deadline::in(30);

    Carbon::setTestNow('2026-01-01 00:00:29');
    expect($deadline->passed())->toBeFalse();

    Carbon::setTestNow('2026-01-01 00:00:30');
    expect($deadline->passed())->toBeTrue();
});

it('beats every heartbeat each time it is asked, in the order they were added', function (): void {
    $beats = [];

    $deadline = Deadline::none()
        ->withHeartbeat(function () use (&$beats): void {
            $beats[] = 'first';
        })
        ->withHeartbeat(function () use (&$beats): void {
            $beats[] = 'second';
        });

    $deadline->passed();
    $deadline->passed();

    expect($beats)->toBe(['first', 'second', 'first', 'second']);
});
