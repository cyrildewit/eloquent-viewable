<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Tests\Support;

use Carbon\Carbon;
use CyrildeWit\EloquentViewable\Exceptions\InvalidPeriod;
use CyrildeWit\EloquentViewable\Support\Period;
use CyrildeWit\EloquentViewable\Tests\TestCase;
use Exception;

class PeriodTest extends TestCase
{
    public function test_it_can_instantiate_class(): void
    {
        $period = $this->app->make(Period::class);

        $this->assertInstanceOf(Period::class, $period);
    }

    public function test_it_can_construct_a_new_period_instance(): void
    {
        $startDateTime = Carbon::yesterday();
        $endDateTime = Carbon::today();

        $period = new Period($startDateTime, $endDateTime);

        $this->assertEquals($period->getStartDateTime(), $startDateTime);
        $this->assertEquals($period->getEndDateTime(), $endDateTime);
    }

    public function test_it_can_construct_a_new_period_instance_with_strings_as_arguments(): void
    {
        $startDateTime = '2018-07-16';
        $endDateTime = '2018-12-23';

        $period = new Period('2018-07-16', '2018-12-23');

        $this->assertEquals($period->getStartDateTime(), Carbon::parse($startDateTime));
        $this->assertEquals($period->getEndDateTime(), Carbon::parse($endDateTime));
    }

    public function test_it_can_construct_a_new_period_instance_with_start_datetime_argument_as_string(): void
    {
        $startDateTime = '2018-07-16';

        $period = new Period('2018-07-16');

        $this->assertEquals($period->getStartDateTime(), Carbon::parse($startDateTime));
        $this->assertNull($period->getEndDateTime());
    }

    public function test_it_can_construct_a_new_period_instance_with_end_datetime_argument_as_string(): void
    {
        $endDateTime = '2018-07-16';

        $period = new Period(null, $endDateTime);

        $this->assertNull($period->getStartDateTime());
        $this->assertEquals($period->getEndDateTime(), Carbon::parse($endDateTime));
    }

    public function test_it_will_throw_an_exception_if_the_start_date_time_comes_after_the_end_date_time(): void
    {
        $startDateTime = Carbon::create(2018);
        $endDateTime = Carbon::create(2017);

        $this->expectException(InvalidPeriod::class);

        new Period($startDateTime, $endDateTime);
    }

    public function static_create_can_construct_a_new_period_instance(): void
    {
        $startDateTime = Carbon::yesterday();
        $endDateTime = Carbon::today();

        $period = Period::create($startDateTime, $endDateTime);

        $this->assertEquals($period->getStartDateTime(), $startDateTime);
        $this->assertEquals($period->getEndDateTime(), $endDateTime);
    }

    public function static_since_can_construct_a_new_period_instance(): void
    {
        $startDateTime = Carbon::yesterday();

        $period = Period::since($startDateTime);

        $this->assertEquals($period->getStartDateTime(), $startDateTime);
        $this->assertNull($period->getEndDateTime());
    }

    public function static_upto_can_construct_a_new_period_instance(): void
    {
        $endDateTime = Carbon::yesterday();

        $period = Period::upto($endDateTime);

        $this->assertNull($period->getStartDateTime());
        $this->assertEquals($period->getEndDateTime(), $endDateTime);
    }

    public function static_pastDays_can_construct_a_new_period_instance(): void
    {
        Carbon::setTestNow(Carbon::now());

        $period = Period::pastDays(5);

        $this->assertEquals($period->getStartDateTime(), Carbon::today()->subDays(5));
        $this->assertNull($period->getEndDateTime());
    }

    public function static_pastWeeks_can_construct_a_new_period_instance(): void
    {
        Carbon::setTestNow(Carbon::now());

        $period = Period::pastWeeks(2);

        $this->assertEquals($period->getStartDateTime(), Carbon::today()->subWeeks(2));
        $this->assertNull($period->getEndDateTime());
    }

    public function static_pastMonths_can_construct_a_new_period_instance(): void
    {
        Carbon::setTestNow(Carbon::now());

        $period = Period::pastMonths(2);

        $this->assertEquals($period->getStartDateTime(), Carbon::today()->subMonths(2));
        $this->assertNull($period->getEndDateTime());
    }

    public function static_pastYears_can_construct_a_new_period_instance(): void
    {
        Carbon::setTestNow(Carbon::now());

        $period = Period::pastYears(2);

        $this->assertEquals($period->getStartDateTime(), Carbon::today()->subYears(2));
        $this->assertNull($period->getEndDateTime());
    }

    public function static_sub_throws_exception_when_subTypeMethod_is_not_callable(): void
    {
        $this->expectException(Exception::class);

        Carbon::setTestNow(Carbon::now());

        Period::sub(Carbon::now(), 'keepDreaming', Period::SUB_SECONDS, 2);
    }

    public function static_subSeconds_can_construct_a_new_period_instance(): void
    {
        Carbon::setTestNow(Carbon::now());

        $period = Period::subSeconds(2);

        $this->assertEquals($period->getStartDateTime(), Carbon::now()->subSeconds(2));
        $this->assertNull($period->getEndDateTime());
    }

    public function static_subMinutes_can_construct_a_new_period_instance(): void
    {
        Carbon::setTestNow(Carbon::now());

        $period = Period::subMinutes(2);

        $this->assertEquals($period->getStartDateTime(), Carbon::now()->subMinutes(2));
        $this->assertNull($period->getEndDateTime());
    }

    public function static_subHours_can_construct_a_new_period_instance(): void
    {
        Carbon::setTestNow(Carbon::now());

        $period = Period::subHours(2);

        $this->assertEquals($period->getStartDateTime(), Carbon::now()->subHours(2));
        $this->assertNull($period->getEndDateTime());
    }

    public function static_subDays_can_construct_a_new_period_instance(): void
    {
        Carbon::setTestNow(Carbon::now());

        $period = Period::subDays(2);

        $this->assertEquals($period->getStartDateTime(), Carbon::now()->subDays(2));
        $this->assertNull($period->getEndDateTime());
    }

    public function static_subWeeks_can_construct_a_new_period_instance(): void
    {
        Carbon::setTestNow(Carbon::now());

        $period = Period::subWeeks(2);

        $this->assertEquals($period->getStartDateTime(), Carbon::now()->subWeeks(2));
        $this->assertNull($period->getEndDateTime());
    }

    public function static_subMonths_can_construct_a_new_period_instance(): void
    {
        Carbon::setTestNow(Carbon::now());

        $period = Period::subMonths(2);

        $this->assertEquals($period->getStartDateTime(), Carbon::now()->subMonths(2));
        $this->assertNull($period->getEndDateTime());
    }

    public function static_subYears_can_construct_a_new_period_instance(): void
    {
        Carbon::setTestNow(Carbon::now());

        $period = Period::subYears(2);

        $this->assertEquals($period->getStartDateTime(), Carbon::now()->subYears(2));
        $this->assertNull($period->getEndDateTime());
    }

    public function static_sub_will_throw_an_exception_if_subtype_method_is_not_callable(): void
    {
        $this->expectException(Exception::class);

        Period::sub(Carbon::now(), 'wrongMethod', Period::SUB_YEARS, 1);
    }

    public function setStartDateTime_can_set_a_new_start_date_time(): void
    {
        Carbon::setTestNow(Carbon::now());

        $period = Period::create();

        $this->assertNull($period->getStartDateTime());

        $period->setStartDateTime(Carbon::now());

        $this->assertEquals($period->getStartDateTime(), Carbon::now());
    }

    public function setEndDateTime_can_set_a_new_start_date_time(): void
    {
        Carbon::setTestNow(Carbon::now());

        $period = Period::create();

        $this->assertNull($period->getEndDateTime());

        $period->setEndDateTime(Carbon::now());

        $this->assertEquals($period->getEndDateTime(), Carbon::now());
    }

    public function hasFixedDateTimes_can_determine_if_datetimes_are_fixed(): void
    {
        $period = Period::pastDays(3);

        $this->assertFalse($period->hasFixedDateTimes());
    }

    public function getStartDateTimeString_returns_start_date_time_as_string(): void
    {
        $period = Period::since($startDateTime = Carbon::parse('2019-03-12'));

        $this->assertEquals($startDateTime->toDateTimeString(), $period->getStartDateTimeString());
    }

    public function getEndDateTimeString_returns_end_date_time_as_string(): void
    {
        $period = Period::upto($endDateTime = Carbon::parse('2019-03-12'));

        $this->assertEquals($endDateTime->toDateTimeString(), $period->getEndDateTimeString());
    }

    public function getSubType_returns_sub_type(): void
    {
        $period = Period::pastDays(3);

        $this->assertEquals(Period::PAST_DAYS, $period->getSubType());
    }

    public function getSubValue_returns_sub_type(): void
    {
        $period = Period::pastDays(3);

        $this->assertEquals(3, $period->getSubValue());
    }
}
