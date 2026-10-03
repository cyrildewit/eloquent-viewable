<?php

declare(strict_types=1);

use CyrildeWit\EloquentViewable\Contracts\Viewable;
use CyrildeWit\EloquentViewable\Tests\Feature\TestCase as FeatureTestCase;
use CyrildeWit\EloquentViewable\Tests\Unit\TestCase as UnitTestCase;
use Illuminate\Database\Eloquent\Model;
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
