<?php

declare(strict_types=1);

use CyrildeWit\EloquentViewable\Doctor\Data\CheckResult;
use CyrildeWit\EloquentViewable\Doctor\Data\Finding;
use CyrildeWit\EloquentViewable\Doctor\Data\Report;
use CyrildeWit\EloquentViewable\Doctor\Data\Status;

/** @param  list<Finding>  $findings */
function reportOf(array $findings): Report
{
    $report = new Report;

    $report->add(new CheckResult('schema', 'Database schema', $findings));

    return $report;
}

it('counts the findings of every check by status', function (): void {
    $report = reportOf([Finding::pass('Fine.'), Finding::warning('Look.'), Finding::warning('Look again.')]);

    $report->add(new CheckResult('cache', 'Cache', [Finding::warning('Shared?')]));

    expect($report->count(Status::Warning))->toBe(3)
        ->and($report->count(Status::Pass))->toBe(1)
        ->and($report->count(Status::Failure))->toBe(0);
});

it('fails on a failure', function (): void {
    expect(reportOf([Finding::failure('Broken.')])->fails(strict: false))->toBeTrue();
});

it('fails on a warning only when strict', function (): void {
    $report = reportOf([Finding::warning('Look.')]);

    expect($report->fails(strict: false))->toBeFalse()
        ->and($report->fails(strict: true))->toBeTrue();
});

it('passes with nothing but passes and advice, even when strict', function (): void {
    expect(reportOf([Finding::pass('Fine.'), Finding::advice('Consider.')])->fails(strict: true))->toBeFalse();
});
