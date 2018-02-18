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

namespace CyrildeWit\EloquentVisitable\Cache;

use Illuminate\Contracts\Cache\Repository;

/**
 * This is the Eloquent model Visit class.
 *
 * @author Cyril de Wit <github@cyrildewit.nl>
 */
class VisitCounterCacheRepository
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
     * Create a new VisitRegistrar instance.
     *
     * @return void
     */
    public function __construct(Repository $cache)
    {
        $this->cache = $cache;
        $this->cacheKey = config('eloquent-visitable.cache-key', 'cyrildewit.eloquent-visitable.cache');
    }

    /**
     * Get the a visit counter from the cache.
     *
     * Cache key structure:
     *  <cache-key>.counters.<visitable-model-type>.<visitable-model-id>.<type>.<period>
     *
     * @return
     */
    public function getVisitCounter($model, string $type, string $period)
    {
        return $this->cache->get($this->createKeyFromModel($model, $type, $period));
    }

    /**
     * Put a new visit counter into the cache.
     *
     * Cache key structure:
     *  <cache-key>.counters.<visitable-model-type>.<visitable-model-id>.<type>.<period>
     *
     * @return
     */
    public function putVisitCounter($model, $value, string $type, string $period, $expiresAt = null)
    {
        $expiresAt = $expiresAt ?? config('eloquent-visitable.cache_expiration_time', 30);

        $key = $this->createKeyFromModel($model, $type, $period);

        return $this->cache->put($key, $value, $expiresAt);
    }

    /**
     * Create a key from the given model, type and period.
     *
     * @return string
     */
    protected function createKeyFromModel($model, string $type, string $period): string
    {
        $modelId = $model->getKey();
        $modelType = get_class($model);

        return "{$this->cacheKey}.counters.{$modelType}.{$modelId}.{$type}.{$period}";
    }
}
