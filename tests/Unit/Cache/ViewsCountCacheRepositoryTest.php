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

use Cache;
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

    /** @test */
    public function it_can_put_a_views_count_in_the_cache()
    {
        $cacheRepository = $this->app->make(ViewsCountCacheRepository::class);

        $cacheRepository->put('something', 3500);

        $this->assertEquals(3500, Cache::get(config('eloquent-viewable.cache.key').'.something'));
    }

    /** @test */
    public function it_can_get_a_views_count_from_the_cache()
    {
        $cacheRepository = $this->app->make(ViewsCountCacheRepository::class);

        Cache::set(config('eloquent-viewable.cache.key').'.something', 1500, 20);

        $this->assertEquals(1500, $cacheRepository->get('something'));
    }

    /** @test */
    public function it_can_check_a_views_count_in_the_cache()
    {
        $cacheRepository = $this->app->make(ViewsCountCacheRepository::class);

        Cache::set(config('eloquent-viewable.cache.key').'.something', 2200, 20);

        $this->assertEquals(true, $cacheRepository->has('something'));
    }
}
