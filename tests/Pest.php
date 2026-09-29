<?php

declare(strict_types=1);

use CyrildeWit\EloquentViewable\Contracts\Viewable;
use CyrildeWit\EloquentViewable\Tests\Feature\TestCase as FeatureTestCase;
use CyrildeWit\EloquentViewable\Tests\Unit\TestCase as UnitTestCase;

pest()->extend(UnitTestCase::class)->in('Unit');

pest()->extend(FeatureTestCase::class)->in('Feature');

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
