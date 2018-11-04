<?php

declare(strict_types=1);

/*
 * This file is part of the Eloquent Viewable package.
 *
 * (c) Cyril de Wit <github@cyrildewit.nl>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace CyrildeWit\EloquentViewable\Tests\Unit\Support;

use Carbon\Carbon;
use CyrildeWit\EloquentViewable\Models\View;
use CyrildeWit\EloquentViewable\Support\Key;
use CyrildeWit\EloquentViewable\Support\Period;
use CyrildeWit\EloquentViewable\Tests\TestCase;

/**
 * Class PeriodTest.
 *
 * @author Cyril de Wit <github@cyrildewit.nl>
 */
class KeyTest extends TestCase
{
    protected function tearDown()
    {
        Carbon::setTestNow();
    }

    /** @test */
    public function makeKey_generates_right_keys()
    {
        $keyOne = Key::createPeriodKey(new Period);
        $this->assertEquals('|', $keyOne);

        $startDateTime = Carbon::now();
        $keyTwo = Key::createPeriodKey(Period::create($startDateTime));
        $this->assertEquals("{$startDateTime->toDateTimeString()}|", $keyTwo);

        $endDateTime = Carbon::now();
        $keyThree = Key::createPeriodKey(Period::create(null, $endDateTime));
        $this->assertEquals("|{$endDateTime->toDateTimeString()}", $keyThree);

        $startDateTime = Carbon::today();
        $keyFour = Key::createPeriodKey(Period::sub($startDateTime, 'subYears', Period::PAST_YEARS, 5));
        $this->assertEquals('past5years|', $keyFour);

        $startDateTime = Carbon::now();
        $keyFive = Key::createPeriodKey(Period::sub($startDateTime, 'subYears', Period::SUB_YEARS, 5));
        $this->assertEquals('sub5years|', $keyFive);
    }
}
