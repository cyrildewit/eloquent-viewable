<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use CyrildeWit\EloquentViewable\Querying\Series\Bucket;
use CyrildeWit\EloquentViewable\Support\Period;

it('is immutable', function (): void {
    $reflection = new ReflectionClass(Bucket::class);

    expect($reflection->isFinal())->toBeTrue()
        ->and($reflection->isReadOnly())->toBeTrue();
});

it('exposes its start, end, count and label', function (): void {
    $start = CarbonImmutable::parse('2026-09-01');
    $end = CarbonImmutable::parse('2026-09-02');

    $bucket = new Bucket($start, $end, 4, '2026-09-01');

    expect($bucket->start)->toBe($start)
        ->and($bucket->end)->toBe($end)
        ->and($bucket->count)->toBe(4)
        ->and($bucket->label)->toBe('2026-09-01');
});

it('converts to a period with the same bounds', function (): void {
    $bucket = new Bucket(CarbonImmutable::parse('2026-09-01'), CarbonImmutable::parse('2026-09-02'), 4, '2026-09-01');

    $period = $bucket->period();

    expect($period)->toBeInstanceOf(Period::class)
        ->and($period->getStartDateTime()->format('Y-m-d H:i:s'))->toBe('2026-09-01 00:00:00')
        ->and($period->getEndDateTime()->format('Y-m-d H:i:s'))->toBe('2026-09-02 00:00:00');
});
