<?php

declare(strict_types=1);

use CyrildeWit\EloquentViewable\CacheKey;
use CyrildeWit\EloquentViewable\Support\Period;
use CyrildeWit\EloquentViewable\Tests\TestClasses\Models\Post;
use Illuminate\Support\Facades\Config;

beforeEach(function () {
    $this->firstPost = Post::factory()->create();
    $this->secondPost = Post::factory()->create();

    Config::set('eloquent-viewable.cache.key', 'test-namespace');
});

it('can make a key from default parameters', function () {
    $firstPostCacheKey = new CacheKey($this->firstPost);
    $secondPostCacheKey = new CacheKey($this->secondPost);

    expect($firstPostCacheKey->make())
        ->toBe('test-namespace:testing::memory::posts:cyrildewiteloquentviewableteststestclassesmodelspost.1.|.normal');

    expect($secondPostCacheKey->make())
        ->toBe('test-namespace:testing::memory::posts:cyrildewiteloquentviewableteststestclassesmodelspost.2.|.normal');
});

it('can make a key from period with startdatetime', function () {
    $firstPostCacheKey = new CacheKey($this->firstPost);
    $secondPostCacheKey = new CacheKey($this->secondPost);

    expect($firstPostCacheKey->make(Period::since('2019-03-21')))
        ->toBe('test-namespace:testing::memory::posts:cyrildewiteloquentviewableteststestclassesmodelspost.1.1553126400|.normal');

    expect($secondPostCacheKey->make(Period::since('2012-04-13')))
        ->toBe('test-namespace:testing::memory::posts:cyrildewiteloquentviewableteststestclassesmodelspost.2.1334275200|.normal');
});

it('can make a key from period with enddatetime', function () {
    $firstPostCacheKey = new CacheKey($this->firstPost);
    $secondPostCacheKey = new CacheKey($this->secondPost);

    expect($firstPostCacheKey->make(Period::upto('2020-07-03')))
        ->toBe('test-namespace:testing::memory::posts:cyrildewiteloquentviewableteststestclassesmodelspost.1.|1593734400.normal');

    expect($secondPostCacheKey->make(Period::upto('2024-09-17')))
        ->toBe('test-namespace:testing::memory::posts:cyrildewiteloquentviewableteststestclassesmodelspost.2.|1726531200.normal');
});

it('can make a key from period with past or sub datetimes', function () {
    $firstPostCacheKey = new CacheKey($this->firstPost);
    $secondPostCacheKey = new CacheKey($this->secondPost);

    expect($firstPostCacheKey->make(Period::pastDays(2)))
        ->toBe('test-namespace:testing::memory::posts:cyrildewiteloquentviewableteststestclassesmodelspost.1.past2days|.normal');

    expect($firstPostCacheKey->make(Period::subSeconds(34)))
        ->toBe('test-namespace:testing::memory::posts:cyrildewiteloquentviewableteststestclassesmodelspost.1.sub34seconds|.normal');

    expect($secondPostCacheKey->make(Period::pastYears(3)))
        ->toBe('test-namespace:testing::memory::posts:cyrildewiteloquentviewableteststestclassesmodelspost.2.past3years|.normal');

    expect($secondPostCacheKey->make(Period::subWeeks(3)))
        ->toBe('test-namespace:testing::memory::posts:cyrildewiteloquentviewableteststestclassesmodelspost.2.sub3weeks|.normal');
});

it('can make a key from type unique', function () {
    $firstPostCacheKey = new CacheKey($this->firstPost);
    $secondPostCacheKey = new CacheKey($this->secondPost);

    expect($firstPostCacheKey->make(null, true))
        ->toBe('test-namespace:testing::memory::posts:cyrildewiteloquentviewableteststestclassesmodelspost.1.|.unique');

    expect($secondPostCacheKey->make(null, true))
        ->toBe('test-namespace:testing::memory::posts:cyrildewiteloquentviewableteststestclassesmodelspost.2.|.unique');
});

it('can make a key from collection', function () {
    $firstPostCacheKey = new CacheKey($this->firstPost);
    $secondPostCacheKey = new CacheKey($this->secondPost);

    expect($firstPostCacheKey->make(null, false, 'some-collection'))
        ->toBe('test-namespace:testing::memory::posts:cyrildewiteloquentviewableteststestclassesmodelspost.1.|.normal.some-collection');

    expect($secondPostCacheKey->make(null, false, 'some-collection'))
        ->toBe('test-namespace:testing::memory::posts:cyrildewiteloquentviewableteststestclassesmodelspost.2.|.normal.some-collection');
});
