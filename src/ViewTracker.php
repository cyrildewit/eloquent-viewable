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

namespace CyrildeWit\EloquentViewable;

use Illuminate\Support\Collection;
use Illuminate\Contracts\Cache\Repository as CacheRepository;

/**
 * Class ViewTracker.
 *
 * @deprecated 3.0.0 This class will be replaced with Views class.
 * @author Cyril de Wit <github@cyrildewit.nl>
 */
class ViewTracker
{
    /**
     * The cache repository instance.
     *
     * @var \Illuminate\Contracts\Cache\Repository
     */
    protected $cache;

    /**
     * Get the total number of views of a viewable type.
     *
     * @param  string  $viewableType
     * @return int
     *
     * @deprecated 3.0.0 Use new Views class
     */
    public static function getViewsCountByType(string $viewableType): int
    {
        $cache = app(CacheRepository::class);
        $cachingEnabled = config('eloquent-viewable.cache.enabled', true);
        $cachingViewTrackerCountsEnabled = config('eloquent-viewable.cache.cache_view_tracker_counts.enabled', true);
        $cacheKey = static::createViewsCountByTypeKey($viewableType);

        // Check cache if wanted
        if ($cachingEnabled && $cachingViewTrackerCountsEnabled) {
            $viewsCountByType = $cache->get($cacheKey);

            if ($viewsCountByType !== null) {
                return $viewsCountByType;
            }
        }

        $viewsCountByType = View::where('viewable_type', $viewableType)->count();

        // Cache the counted views
        if ($cachingEnabled) {
            $lifetime = config('eloquent-viewable.cache.cache_view_tracker_counts.lifetime_in_minutes', 60);
            $cache->put($cacheKey, $viewsCountByType, $lifetime);
        }

        return $viewsCountByType;
    }

    /**
     * Get the total number of views of a viewable type.
     *
     * @param  array|Illuminate\Support\Collection  Collection of viewable models.
     * @return array|Illuminate\Support\Collection
     *
     * @deprecated 3.0.0 Use new Views class
     */
    public static function getViewsCountByTypes($viewableTypes)
    {
        $viewsCountTypes = Collection::make([]);
        $viewableTypes = Collection::make($viewableTypes);

        foreach ($viewableTypes as $viewableType) {
            $viewsCountTypes->put(
                $viewableType,
                static::getViewsCountByType($viewableType)
            );
        }

        return $viewsCountTypes;
    }

    /**
     * Create a cache key for a views count by type.
     *
     * @param  string  $viewableType
     * @return string
     */
    protected static function createViewsCountByTypeKey(string $viewableType): string
    {
        $cacheKey = config('eloquent-viewable.cache.key', 'cyrildewit.eloquent-viewable.cache');
        $suffix = strtolower(str_replace('\\', '-', $viewableType));

        return "{$cacheKey}.{$suffix}";
    }
}
