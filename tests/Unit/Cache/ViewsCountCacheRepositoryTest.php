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

namespace CyrildeWit\EloquentViewable\Tests\Unit\Cache;

use CyrildeWit\EloquentViewable\Tests\TestCase;
use CyrildeWit\EloquentViewable\Cache\ViewsCountCacheRepository;

/**
 * Class ViewsCountCacheRepositoryTest.
 *
 * @author Cyril de Wit <github@cyrildewit.nl>
 */
class ViewsCountCacheRepositoryTest extends TestCase
{
    /** @test */
    public function it_can_instantiate_cache_repository()
    {
        $cacheRepository = $this->app->make(ViewsCountCacheRepository::class);

        $this->assertInstanceOf(ViewsCountCacheRepository::class, $cacheRepository);
    }
}
