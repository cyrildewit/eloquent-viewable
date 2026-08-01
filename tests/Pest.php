<?php

declare(strict_types=1);

use Carbon\Carbon;
use CyrildeWit\EloquentViewable\Tests\TestCase;

uses(TestCase::class)->in(__DIR__);

afterEach(function (): void {
    Mockery::close();
    Carbon::setTestNow();
});
