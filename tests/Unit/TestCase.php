<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Tests\Unit;

use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    use MockeryPHPUnitIntegration;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }
}
