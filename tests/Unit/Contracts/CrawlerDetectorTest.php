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

namespace CyrildeWit\EloquentViewable\Tests\Unit;

use CyrildeWit\EloquentViewable\Tests\TestCase;
use CyrildeWit\EloquentViewable\Contracts\CrawlerDetector;

/**
 * Class CrawlerDetectorTest.
 *
 * @author Cyril de Wit <github@cyrildewit.nl>
 */
class CrawlerDetectorTest extends TestCase
{
    /** @test */
    public function it_can_instantiate_class()
    {
        // Faking that the visitor is a bot
        $this->app->bind(CrawlerDetector::class, function () {
            return new class implements CrawlerDetector {
                public function isBot()
                {
                    return true;
                }
            };
        });

        $detector = $this->app->make(CrawlerDetector::class);

        $this->assertInstanceOf(CrawlerDetector::class, $detector);
    }

    /** @test */
    public function isBot_returns_true_if_the_visitor_is_a_bot()
    {
        // Faking that the visitor is a bot
        $this->app->bind(CrawlerDetector::class, function () {
            return new class implements CrawlerDetector {
                public function isBot()
                {
                    return true;
                }
            };
        });

        $detector = $this->app->make(CrawlerDetector::class);

        $this->assertTrue($detector->isBot());
    }
}
