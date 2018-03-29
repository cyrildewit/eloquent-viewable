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

namespace CyrildeWit\EloquentViewable\Cache;

use Illuminate\Contracts\Cache\Repository;

/**
 * Class ViewsAnalyticsRepository.
 *
 * @author Cyril de Wit <github@cyrildewit.nl>
 */
class ViewsAnalyticsCacheRepository
{
    /**
     * The cache repository instance.
     *
     * @var \Illuminate\Contracts\Cache\Repository
     */
    protected $cache;

    /**
     * The used cache key.
     *
     * @var string
     */
    protected $cacheKey;

    /**
     * Create a new ViewsAnalyticsCacheRepository instance.
     *
     * @return void
     */
    public function __construct(Repository $cache)
    {
        $this->cache = $cache;
        $this->cacheKey = config('eloquent-viewable.cache.key', 'cyrildewit.eloquent-viewable.cache');
    }

    /**
     * Determine if a views count exists in the cache.
     *
     * @param  string  $key
     * @return bool
     */
    public function hasViewsCount($key): bool
    {
        return $this->cache->has("{$this->cacheKey}.{$key}");
    }

    /**
     * Retrieve a views count from the cache.
     *
     * @param  string  $key
     * @return mixed
     */
    public function getViewsCount($key)
    {
        return $this->cache->get("{$this->cacheKey}.{$key}");
    }

    /**
     * Store a views count in the cache.
     *
     * @param  string  $type
     * @param  int  $viewsCount
     * @param  \DateTimeInterface|\DateInterval|float|int  $minutes
     * @return void
     */
    public function putViewsCount($key, int $viewsCount, $minutes = null)
    {
        $minutes = $minutes ?? config('eloquent-viewable.cache.cache_views_count.default_lifetime_in_minutes', 10);

        $this->cache->put("{$this->cacheKey}.{$key}", $viewsCount, $minutes);
    }
}
