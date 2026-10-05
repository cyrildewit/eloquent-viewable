<?php

declare(strict_types=1);

use CyrildeWit\EloquentViewable\Recording\Contracts\RecordingGuard;
use CyrildeWit\EloquentViewable\Recording\Data\RecordResult;
use CyrildeWit\EloquentViewable\Recording\Guards\EnforceCooldown;
use CyrildeWit\EloquentViewable\Tests\Fixtures\Guards\RefuseAll;

it('describes a stored view', function (): void {
    $result = RecordResult::stored();

    expect($result->recorded)->toBeTrue()
        ->and($result->queued)->toBeFalse()
        ->and($result->skippedBy)->toBeNull();
});

it('describes a queued view', function (): void {
    $result = RecordResult::queued();

    expect($result->recorded)->toBeTrue()
        ->and($result->queued)->toBeTrue()
        ->and($result->skippedBy)->toBeNull();
});

it('describes a skipped view and the guard that refused it', function (): void {
    $guard = Mockery::mock(RecordingGuard::class);

    $result = RecordResult::skipped($guard);

    expect($result->recorded)->toBeFalse()
        ->and($result->queued)->toBeFalse()
        ->and($result->skippedBy)->toBe($guard);
});

it('tells whether a given guard class skipped the view', function (): void {
    $guard = new RefuseAll;

    expect(RecordResult::skipped($guard)->wasSkippedBy(RefuseAll::class))->toBeTrue()
        ->and(RecordResult::skipped($guard)->wasSkippedBy(EnforceCooldown::class))->toBeFalse()
        ->and(RecordResult::stored()->wasSkippedBy(RefuseAll::class))->toBeFalse();
});

it('names the guard that skipped the view by its class in JSON', function (): void {
    expect(json_encode(RecordResult::skipped(new RefuseAll)))->toBe(json_encode([
        'recorded' => false,
        'queued' => false,
        'skipped_by' => RefuseAll::class,
    ]))
        ->and(RecordResult::queued()->jsonSerialize())->toBe(['recorded' => true, 'queued' => true, 'skipped_by' => null]);
});
