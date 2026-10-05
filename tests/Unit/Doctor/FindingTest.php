<?php

declare(strict_types=1);

use CyrildeWit\EloquentViewable\Doctor\Data\Finding;
use CyrildeWit\EloquentViewable\Doctor\Data\Status;

it('builds a finding for every status', function (Finding $finding, Status $status): void {
    expect($finding->status)->toBe($status);
})->with([
    'pass' => [fn (): Finding => Finding::pass('Fine.'), Status::Pass],
    'advice' => [fn (): Finding => Finding::advice('Consider it.'), Status::Advice],
    'warning' => [fn (): Finding => Finding::warning('Look at it.'), Status::Warning],
    'failure' => [fn (): Finding => Finding::failure('Fix it.'), Status::Failure],
    'skipped' => [fn (): Finding => Finding::skipped('Not used.'), Status::Skipped],
]);

it('keeps the fix next to the summary', function (): void {
    expect(Finding::failure('The views table is missing.', 'Run the migration.')->toArray())->toBe([
        'status' => 'failure',
        'summary' => 'The views table is missing.',
        'fix' => 'Run the migration.',
    ]);
});

it('leaves the fix out of a pass and a skipped check', function (): void {
    expect(Finding::pass('Fine.')->fix)->toBeNull()
        ->and(Finding::skipped('Not used.')->fix)->toBeNull();
});

it('gives every status an icon and a colour', function (Status $status, string $icon, string $color): void {
    expect($status->icon())->toBe($icon)
        ->and($status->color())->toBe($color);
})->with([
    [Status::Pass, '✓', 'green'],
    [Status::Advice, 'i', 'blue'],
    [Status::Warning, '!', 'yellow'],
    [Status::Failure, '✗', 'red'],
    [Status::Skipped, '-', 'gray'],
]);
