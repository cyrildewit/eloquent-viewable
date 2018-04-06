<?php

declare(strict_types=1);

/*
 * This file is part of Eloquent Viewable.
 *
 * (c) Cyril de Wit <github@cyrildewit.nl>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace CyrildeWit\EloquentViewable\Tests\Unit\Services;

use Config;
use Request;
use Carbon\Carbon;
use CyrildeWit\EloquentViewable\Models\View;
use CyrildeWit\EloquentViewable\Support\Period;
use CyrildeWit\EloquentViewable\Tests\TestCase;
use CyrildeWit\EloquentViewable\Tests\TestHelper;
use CyrildeWit\EloquentViewable\Support\IpAddress;
use CyrildeWit\EloquentViewable\Support\CrawlerDetector;
use CyrildeWit\EloquentViewable\Exceptions\InvalidPeriod;
use CyrildeWit\EloquentViewable\Tests\Stubs\Models\Post;
use CyrildeWit\EloquentViewable\Services\ViewableService;

/**
 * Class PeriodTest.
 *
 * @author Cyril de Wit <github@cyrildewit.nl>
 */
class PeriodTest extends TestCase
{
    protected function tearDown()
    {
        Carbon::setTestNow();
    }

    /** @test */
    public function it_can_instantiate_helper()
    {
        $helper = $this->app->make(Period::class);

        $this->assertInstanceOf(Period::class, $helper);
    }

    /** @test */
    public function it_can_construct_a_new_period_instance()
    {
        $startDateTime = Carbon::yesterday();
        $endDateTime = Carbon::today();

        $period = new Period($startDateTime, $endDateTime);

        $this->assertEquals($period->getStartDateTime(), $startDateTime);
        $this->assertEquals($period->getEndDateTime(), $endDateTime);
    }

    /** @test */
    public function it_will_throw_an_exception_if_the_start_date_time_comes_after_the_end_date_time()
    {
        $startDateTime = Carbon::create(2018, 1, 1);
        $endDateTime = Carbon::create(2017, 1, 1);

        $this->expectException(InvalidPeriod::class);

        $period = new Period($startDateTime, $endDateTime);
    }

    /** @test */
    public function static_create_can_construct_a_new_period_instance()
    {
        $startDateTime = Carbon::yesterday();
        $endDateTime = Carbon::today();

        $period = Period::create($startDateTime, $endDateTime);

        $this->assertEquals($period->getStartDateTime(), $startDateTime);
        $this->assertEquals($period->getEndDateTime(), $endDateTime);
    }

    /** @test */
    public function static_since_can_construct_a_new_period_instance()
    {
        $startDateTime = Carbon::yesterday();

        $period = Period::since($startDateTime);

        $this->assertEquals($period->getStartDateTime(), $startDateTime);
        $this->assertEquals($period->getEndDateTime(), null);
    }

    /** @test */
    public function static_upto_can_construct_a_new_period_instance()
    {
        $endDateTime = Carbon::yesterday();

        $period = Period::upto($endDateTime);

        $this->assertEquals($period->getStartDateTime(), null);
        $this->assertEquals($period->getEndDateTime(), $endDateTime);
    }

    /** @test */
    public function static_pastDays_can_construct_a_new_period_instance()
    {
        Carbon::setTestNow(Carbon::now());

        $period = Period::pastDays(5);

        $this->assertEquals($period->getStartDateTime(), Carbon::today()->subDays(5));
        $this->assertEquals($period->getEndDateTime(), null);
    }

    /** @test */
    public function static_pastWeeks_can_construct_a_new_period_instance()
    {
        Carbon::setTestNow(Carbon::now());

        $period = Period::pastWeeks(2);

        $this->assertEquals($period->getStartDateTime(), Carbon::today()->subWeeks(2));
        $this->assertEquals($period->getEndDateTime(), null);
    }

    /** @test */
    public function static_pastMonths_can_construct_a_new_period_instance()
    {
        Carbon::setTestNow(Carbon::now());

        $period = Period::pastMonths(2);

        $this->assertEquals($period->getStartDateTime(), Carbon::today()->subMonths(2));
        $this->assertEquals($period->getEndDateTime(), null);
    }

    /** @test */
    public function static_pastYears_can_construct_a_new_period_instance()
    {
        Carbon::setTestNow(Carbon::now());

        $period = Period::pastYears(2);

        $this->assertEquals($period->getStartDateTime(), Carbon::today()->subYears(2));
        $this->assertEquals($period->getEndDateTime(), null);
    }

    /** @test */
    public function static_subSeconds_can_construct_a_new_period_instance()
    {
        Carbon::setTestNow(Carbon::now());

        $period = Period::subSeconds(2);

        $this->assertEquals($period->getStartDateTime(), Carbon::now()->subSeconds(2));
        $this->assertEquals($period->getEndDateTime(), null);
    }

    /** @test */
    public function static_subMinutes_can_construct_a_new_period_instance()
    {
        Carbon::setTestNow(Carbon::now());

        $period = Period::subMinutes(2);

        $this->assertEquals($period->getStartDateTime(), Carbon::now()->subMinutes(2));
        $this->assertEquals($period->getEndDateTime(), null);
    }

    /** @test */
    public function static_subHours_can_construct_a_new_period_instance()
    {
        Carbon::setTestNow(Carbon::now());

        $period = Period::subHours(2);

        $this->assertEquals($period->getStartDateTime(), Carbon::now()->subHours(2));
        $this->assertEquals($period->getEndDateTime(), null);
    }

    /** @test */
    public function static_subDays_can_construct_a_new_period_instance()
    {
        Carbon::setTestNow(Carbon::now());

        $period = Period::subDays(2);

        $this->assertEquals($period->getStartDateTime(), Carbon::now()->subDays(2));
        $this->assertEquals($period->getEndDateTime(), null);
    }

    /** @test */
    public function static_subWeeks_can_construct_a_new_period_instance()
    {
        Carbon::setTestNow(Carbon::now());

        $period = Period::subWeeks(2);

        $this->assertEquals($period->getStartDateTime(), Carbon::now()->subWeeks(2));
        $this->assertEquals($period->getEndDateTime(), null);
    }

    /** @test */
    public function static_subMonths_can_construct_a_new_period_instance()
    {
        Carbon::setTestNow(Carbon::now());

        $period = Period::subMonths(2);

        $this->assertEquals($period->getStartDateTime(), Carbon::now()->subMonths(2));
        $this->assertEquals($period->getEndDateTime(), null);
    }

    /** @test */
    public function static_subYears_can_construct_a_new_period_instance()
    {
        Carbon::setTestNow(Carbon::now());

        $period = Period::subYears(2);

        $this->assertEquals($period->getStartDateTime(), Carbon::now()->subYears(2));
        $this->assertEquals($period->getEndDateTime(), null);
    }

    /** @test */
    public function static_sub_returns_null_if_subTypeMethod_is_not_callable()
    {
        $period = Period::sub(Carbon::now(), 'random', 'unkown', 5);

        $this->assertNull($period);
    }

    /** @test */
    public function setStartDateTime_can_set_a_new_start_date_time()
    {
        Carbon::setTestNow(Carbon::now());

        $period = Period::create();

        $this->assertNull($period->getStartDateTime());

        $period->setStartDateTime(Carbon::now());

        $this->assertEquals($period->getStartDateTime(), Carbon::now());
    }

    /** @test */
    public function setEndDateTime_can_set_a_new_start_date_time()
    {
        Carbon::setTestNow(Carbon::now());

        $period = Period::create();

        $this->assertNull($period->getEndDateTime());

        $period->setEndDateTime(Carbon::now());

        $this->assertEquals($period->getEndDateTime(), Carbon::now());
    }

    /** @test */
    public function makeKey_generates_right_keys()
    {
        $keyOne = Period::create()->makeKey();
        $this->assertEquals('|', $keyOne);

        $startDateTime = Carbon::now();
        $keyTwo = Period::create($startDateTime)->makeKey();
        $this->assertEquals("{$startDateTime->toDateTimeString()}|", $keyTwo);

        $endDateTime = Carbon::now();
        $keyThree = Period::create(null, $endDateTime)->makeKey();
        $this->assertEquals("|{$endDateTime->toDateTimeString()}", $keyThree);

        $startDateTime = Carbon::today();
        $keyFour = Period::sub($startDateTime, 'subYears', Period::PAST_YEARS, 5)->makeKey();
        $this->assertEquals("past5years|", $keyFour);

        $startDateTime = Carbon::now();
        $keyFive = Period::sub($startDateTime, 'subYears', Period::SUB_YEARS, 5)->makeKey();
        $this->assertEquals("sub5years|", $keyFive);
    }
}
