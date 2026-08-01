<?php

declare(strict_types=1);

use CyrildeWit\EloquentViewable\Contracts\Viewable;
use CyrildeWit\EloquentViewable\Tests\TestCase;

pest()->extend(TestCase::class)->in(__DIR__);

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
