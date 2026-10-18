<?php

declare(strict_types=1);

use Carbon\Carbon;
use CyrildeWit\EloquentViewable\Support\Chunks;
use CyrildeWit\EloquentViewable\Support\Deadline;
use Illuminate\Support\Collection;

afterEach(function (): void {
    Carbon::setTestNow();
});

it('applies chunks until one comes back short', function (): void {
    $batches = [[1, 2], [3, 4], [5]];
    $applied = [];

    $progress = Chunks::run(
        Deadline::none(),
        2,
        function () use (&$batches): array {
            return array_shift($batches) ?? [];
        },
        function (array $batch) use (&$applied): int {
            $applied[] = $batch;

            return count($batch);
        },
    );

    expect($applied)->toBe([[1, 2], [3, 4], [5]])
        ->and($progress->count)->toBe(5)
        ->and($progress->stopped)->toBeFalse();
});

it('counts the rows apply reports rather than the size of the chunk', function (): void {
    $batches = [[1, 2], [3]];

    $progress = Chunks::run(
        Deadline::none(),
        2,
        function () use (&$batches): array {
            return array_shift($batches) ?? [];
        },
        fn (array $batch): int => 1,
    );

    expect($progress->count)->toBe(2);
});

it('fetches once more after a full chunk and skips apply when that comes back empty', function (): void {
    $batches = [[1, 2], []];
    $fetches = 0;
    $applied = [];

    $progress = Chunks::run(
        Deadline::none(),
        2,
        function () use (&$batches, &$fetches): array {
            $fetches++;

            return array_shift($batches) ?? [];
        },
        function (array $batch) use (&$applied): int {
            $applied[] = $batch;

            return count($batch);
        },
    );

    expect($fetches)->toBe(2)
        ->and($applied)->toBe([[1, 2]])
        ->and($progress->count)->toBe(2)
        ->and($progress->stopped)->toBeFalse();
});

it('fetches nothing when the deadline has passed before the first chunk', function (): void {
    Carbon::setTestNow('2026-01-01 00:00:00');
    $fetched = false;

    $progress = Chunks::run(
        Deadline::in(0),
        2,
        function () use (&$fetched): array {
            $fetched = true;

            return [1, 2];
        },
        fn (array $batch): int => count($batch),
    );

    expect($fetched)->toBeFalse()
        ->and($progress->count)->toBe(0)
        ->and($progress->stopped)->toBeTrue();
});

it('finishes the chunk in progress and stops before the next once the deadline passes', function (): void {
    Carbon::setTestNow('2026-01-01 00:00:00');
    $deadline = Deadline::in(30);
    $fetches = 0;

    $progress = Chunks::run(
        $deadline,
        2,
        function () use (&$fetches): array {
            $fetches++;

            return [1, 2];
        },
        function (array $batch): int {
            Carbon::setTestNow('2026-01-01 00:00:30');

            return count($batch);
        },
    );

    expect($fetches)->toBe(1)
        ->and($progress->count)->toBe(2)
        ->and($progress->stopped)->toBeTrue();
});

it('takes chunks that are countable rather than arrays', function (): void {
    $batches = [collect([1, 2]), collect([3])];

    $progress = Chunks::run(
        Deadline::none(),
        2,
        function () use (&$batches): Collection {
            return array_shift($batches) ?? collect();
        },
        fn (Collection $batch): int => $batch->count(),
    );

    expect($progress->count)->toBe(3)
        ->and($progress->stopped)->toBeFalse();
});
