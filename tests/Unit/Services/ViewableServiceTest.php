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

namespace CyrildeWit\EloquentViewable\Tests\Unit\Traits;

use CyrildeWit\EloquentViewable\Tests\TestCase;
use CyrildeWit\EloquentViewable\Services\ViewableService;

/**
 * Class ViewableServiceTest.
 *
 * @author Cyril de Wit <github@cyrildewit.nl>
 */
class ViewableServiceTest extends TestCase
{
    /** @test */
    public function it_can_instantiate_service()
    {
        $service = $this->app->make(ViewableService::class);

        $this->assertInstanceOf(ViewableService::class, $service);
    }
}
