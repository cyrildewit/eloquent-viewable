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

namespace CyrildeWit\EloquentViewable;

use Carbon\Carbon;
use Illuminate\Support\Collection;
use CyrildeWit\EloquentViewable\Models\View;
use Illuminate\Contracts\Cache\Repository as CacheRepository;

/**
 * Class ViewsAnalytics.
 *
 * @author Cyril de Wit <github@cyrildewit.nl>
 */
class ViewsAnalytics
{
    /**
     * The cache repository instance.
     *
     * @var \Illuminate\Contracts\Cache\Repository
     */
    protected $cache;

    /**
     * Create a new ViewsAnalytics instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->cache = app(CacheRepository::class);
    }

    /**
     * Get the total number of views of a viewable type.
     *
     * @param  string  $viewableType
     * @return int
     */
    public function getViewsCountByType(string $viewableType): int
    {
        $cachingEnabled = config('eloquent-viewable.cache.enabled', true);
        $cachingAnalytisViewsCountEnabled = config('eloquent-viewable.cache.cache_analytics_views_count.enabled', true);
        $cacheKey = strtolower(str_replace('\\', '-', $viewableType));

        if ($cachingEnabled && $cachingAnalytisViewsCountEnabled) {
            if (! is_null($viewsCountByType = $this->cache->get($cacheKey))) {
                return $viewsCountByType;
            }
        }

        $viewsCountByType = View::where('viewable_type', $viewableType)->count();

         // Cache the counted views
        if ($cachingEnabled) {
            $this->cache->put($cacheKey, $viewsCountByType);
        }

        return $viewsCountByType;
    }

    /**
     * Get the total number of views of a viewable type.
     *
     * @param  array|Illuminate\Support\Collection  Collection of viewable models.
     * @return array|Illuminate\Support\Collection
     */
    public function getViewsCountByTypes($viewableTypes)
    {
        $viewsCountTypes = Collection::make([]);
        $viewableTypes = Collection::make($viewableTypes);

        foreach ($viewableTypes as $viewableType) {
            $viewsCountTypes->put(
                $viewableType,
                $this->getViewsCountByType($viewableType)
            );
        }

        return $viewsCountTypes;
    }
}
