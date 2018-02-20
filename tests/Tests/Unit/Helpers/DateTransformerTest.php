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

use Config;
use Carbon\Carbon;
use CyrildeWit\EloquentVisitable\Tests\TestCase;
use CyrildeWit\EloquentVisitable\Helpers\DateTransformer;

/**
 * Class DateTransformerTest.
 *
 * @author Cyril de Wit <github@cyrildewit.nl>
 */
class DateTransformerTest extends TestCase
{
    /** @test */
    public function it_can_instantiate_helper()
    {
        $helper = $this->app->make(DateTransformer::class);

        $this->assertInstanceOf(DateTransformer::class, $helper);
    }

    /** @test */
    public function it_can_transform_strings_correctly_to_carbon_objects()
    {
        $dateTransformer = app(DateTransformer::class);

        $pastDay = Carbon::now()->subDay(1);
        $pastWeek = Carbon::now()->subWeek(1);
        $pastMonth = Carbon::now()->subMonth(1);

        // Store these date transformers in the config
        Config::set($this->configFileName.'.date-transformers', [
            'pastDay' => $pastDay,
            'pastWeek' => $pastWeek,
            'pastMonth' => $pastMonth,
        ]);

        // Test if the outcomes are equal as expected
        $this->assertEquals($pastDay, $dateTransformer->transform('pastDay'));
        $this->assertEquals($pastWeek, $dateTransformer->transform('pastWeek'));
        $this->assertEquals($pastMonth, $dateTransformer->transform('pastMonth'));
    }
}
