<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Tests;

use CyrildeWit\EloquentViewable\CacheKey;
use CyrildeWit\EloquentViewable\Support\Period;
use CyrildeWit\EloquentViewable\Tests\TestClasses\Models\Post;
use Illuminate\Support\Facades\Config;

class CacheKeyTest extends TestCase
{
    protected Post $post;

    public function setUp(): void
    {
        parent::setUp();

        $this->firstPost = Post::factory()->create();
        $this->secondPost = Post::factory()->create();

        Config::set('eloquent-viewable.cache.key', 'test-namespace');
    }

    public function test_it_can_make_a_key_from_default_parameters(): void
    {
        $firstPostCacheKey = new CacheKey($this->firstPost);
        $secondPostCacheKey = new CacheKey($this->secondPost);

        $this->assertEquals(
            'test-namespace:sqlite::memory::posts:cyrildewiteloquentviewableteststestclassesmodelspost.1.|.normal',
            $firstPostCacheKey->make()
        );

        $this->assertEquals(
            'test-namespace:sqlite::memory::posts:cyrildewiteloquentviewableteststestclassesmodelspost.2.|.normal',
            $secondPostCacheKey->make()
        );
    }

    public function test_it_can_make_a_key_from_period_with_startdatetime(): void
    {
        $firstPostCacheKey = new CacheKey($this->firstPost);
        $secondPostCacheKey = new CacheKey($this->secondPost);

        $this->assertEquals(
            'test-namespace:sqlite::memory::posts:cyrildewiteloquentviewableteststestclassesmodelspost.1.1553126400|.normal',
            $firstPostCacheKey->make(Period::since('2019-03-21'))
        );

        $this->assertEquals(
            'test-namespace:sqlite::memory::posts:cyrildewiteloquentviewableteststestclassesmodelspost.2.1334275200|.normal',
            $secondPostCacheKey->make(Period::since('2012-04-13'))
        );
    }

    public function test_it_can_make_a_key_from_period_with_enddatetime(): void
    {
        $firstPostCacheKey = new CacheKey($this->firstPost);
        $secondPostCacheKey = new CacheKey($this->secondPost);

        $this->assertEquals(
            'test-namespace:sqlite::memory::posts:cyrildewiteloquentviewableteststestclassesmodelspost.1.|1593734400.normal',
            $firstPostCacheKey->make(Period::upto('2020-07-03'))
        );

        $this->assertEquals(
            'test-namespace:sqlite::memory::posts:cyrildewiteloquentviewableteststestclassesmodelspost.2.|1726531200.normal',
            $secondPostCacheKey->make(Period::upto('2024-09-17'))
        );
    }

    public function test_it_can_make_a_key_from_period_with_past_or_sub_datetimes(): void
    {
        $firstPostCacheKey = new CacheKey($this->firstPost);
        $secondPostCacheKey = new CacheKey($this->secondPost);

        $this->assertEquals(
            'test-namespace:sqlite::memory::posts:cyrildewiteloquentviewableteststestclassesmodelspost.1.past2days|.normal',
            $firstPostCacheKey->make(Period::pastDays(2))
        );

        $this->assertEquals(
            'test-namespace:sqlite::memory::posts:cyrildewiteloquentviewableteststestclassesmodelspost.1.sub34seconds|.normal',
            $firstPostCacheKey->make(Period::subSeconds(34))
        );

        $this->assertEquals(
            'test-namespace:sqlite::memory::posts:cyrildewiteloquentviewableteststestclassesmodelspost.2.past3years|.normal',
            $secondPostCacheKey->make(Period::pastYears(3))
        );

        $this->assertEquals(
            'test-namespace:sqlite::memory::posts:cyrildewiteloquentviewableteststestclassesmodelspost.2.sub3weeks|.normal',
            $secondPostCacheKey->make(Period::subWeeks(3))
        );
    }

    public function test_it_can_make_a_key_from_type_unique(): void
    {
        $firstPostCacheKey = new CacheKey($this->firstPost);
        $secondPostCacheKey = new CacheKey($this->secondPost);

        $this->assertEquals(
            'test-namespace:sqlite::memory::posts:cyrildewiteloquentviewableteststestclassesmodelspost.1.|.unique',
            $firstPostCacheKey->make(null, true)
        );

        $this->assertEquals(
            'test-namespace:sqlite::memory::posts:cyrildewiteloquentviewableteststestclassesmodelspost.2.|.unique',
            $secondPostCacheKey->make(null, true)
        );
    }

    public function test_it_can_make_a_key_from_collection(): void
    {
        $firstPostCacheKey = new CacheKey($this->firstPost);
        $secondPostCacheKey = new CacheKey($this->secondPost);

        $this->assertEquals(
            'test-namespace:sqlite::memory::posts:cyrildewiteloquentviewableteststestclassesmodelspost.1.|.normal.some-collection',
            $firstPostCacheKey->make(null, false, 'some-collection')
        );

        $this->assertEquals(
            'test-namespace:sqlite::memory::posts:cyrildewiteloquentviewableteststestclassesmodelspost.2.|.normal.some-collection',
            $secondPostCacheKey->make(null, false, 'some-collection')
        );
    }
}
