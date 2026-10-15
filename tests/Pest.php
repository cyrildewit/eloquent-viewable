<?php

declare(strict_types=1);

use Carbon\Carbon;
use CyrildeWit\EloquentViewable\Contracts\Viewable;
use CyrildeWit\EloquentViewable\Support\Deadline;
use CyrildeWit\EloquentViewable\Tests\Feature\TestCase as FeatureTestCase;
use CyrildeWit\EloquentViewable\Tests\Unit\TestCase as UnitTestCase;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

pest()->extend(UnitTestCase::class)->in('Unit');

pest()->extend(FeatureTestCase::class)->in('Feature', '../samples');

/**
 * The driver the suite is running against. SQL string assertions are written
 * for the SQLite grammar and skip on the other drivers.
 */
function driver(): string
{
    return DB::connection()->getDriverName();
}

/**
 * The keys of the given models, in order. Ordering assertions name the models
 * they expect rather than literal ids, which only start at 1 on a database
 * created fresh for the test.
 *
 * @return Collection<int, mixed>
 */
function keysOf(Model ...$models): Collection
{
    return new Collection(array_map(static fn (Model $model): mixed => $model->getKey(), $models));
}

/**
 * Returns a deadline that passes once it has been asked more than this many
 * times, so a test can stop a run after a given number of units of work.
 * Asking it once too often moves the clock past it.
 */
function deadlineAfter(int $checks): Deadline
{
    $asked = 0;

    return Deadline::in(60)->withHeartbeat(function () use (&$asked, $checks): void {
        if (++$asked === $checks + 1) {
            Carbon::setTestNow(Carbon::now()->addMinutes(2));
        }
    });
}

/**
 * Moves the clock ten minutes on, once, the first time a statement that
 * starts with the prefix runs on the table, so a command's own deadline
 * passes.
 */
function travelOnFirst(string $prefix, string $table): void
{
    $travelled = false;

    DB::listen(function (QueryExecuted $query) use ($prefix, $table, &$travelled): void {
        if ($travelled) {
            return;
        }

        if (! str_starts_with($query->sql, $prefix)) {
            return;
        }

        if (! str_contains($query->sql, $table)) {
            return;
        }

        $travelled = true;

        Carbon::setTestNow(Carbon::now()->addMinutes(10));
    });
}

expect()->extend('toHaveViewsCount', function (int $count): void {
    /** @var Viewable $viewable */
    $viewable = $this->value;

    expect(views($viewable)->count())->toBe($count);
});

expect()->extend('toHaveUniqueViewsCount', function (int $count): void {
    /** @var Viewable $viewable */
    $viewable = $this->value;

    expect(views($viewable)->unique()->count())->toBe($count);
});
