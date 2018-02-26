<?php

declare(strict_types=1);

/*
 * This file is part of Eloquent Visitable.
 *
 * (c) Cyril de Wit <github@cyrildewit.nl>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace CyrildeWit\EloquentVisitable\Tests\Unit\Helpers;

use Carbon\Carbon;
use CyrildeWit\EloquentVisitable\Tests\TestCase;
use CyrildeWit\EloquentVisitable\Helpers\Serializer;

/**
 * Class SerializerTest.
 *
 * @author Cyril de Wit <github@cyrildewit.nl>
 */
class SerializerTest extends TestCase
{
    /** @test */
    public function it_can_instantiate_helper()
    {
        $helper = $this->app->make(Serializer::class);

        $this->assertInstanceOf(Serializer::class, $helper);
    }

    /** @test */
    public function it_returns_the_right_type()
    {
        $serializer = $this->app->make(Serializer::class);

        // Test 'normal' type output
        $uniqueOption = false;
        $output = $serializer->createType($uniqueOption);
        $this->assertEquals('normal', $output);

        // Test 'unique' type output
        $uniqueOption = true;
        $output = $serializer->createType($uniqueOption);
        $this->assertEquals('unique', $output);
    }

    /** @test */
    public function it_returns_the_right_period()
    {
        $serializer = $this->app->make(Serializer::class);
        $now = Carbon::now();

        // Test since=null, upto=null
        $sinceDate = null;
        $uptoDate = null;
        $output = $serializer->createPeriod($sinceDate, $uptoDate, $now);
        $this->assertEquals('|', $output);

        // Test since=xxx, upto=null
        $sinceDate = $now->copy()->subDays(4);
        $uptoDate = null;
        $output = $serializer->createPeriod($sinceDate, $uptoDate, $now);
        $this->assertEquals('345600|', $output);

        // Test since=null, upto=xxx
        $sinceDate = null;
        $uptoDate = $now->copy()->subDays(4);
        $output = $serializer->createPeriod($sinceDate, $uptoDate, $now);
        $this->assertEquals('|345600', $output);

        // Test since=xxx, upto=xxx
        $sinceDate = $now->copy()->subMonths(4);
        $uptoDate = $now->copy()->subMonths(1);
        $output = $serializer->createPeriod($sinceDate, $uptoDate, $now);
        $this->assertEquals('10627200|2678400', $output);
    }
}
