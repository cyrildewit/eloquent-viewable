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
        $this->cacheKey = config('eloquent-visitable.cache.key', 'cyrildewit.eloquent-visitable.cache');
    }

    /**
     * Determine if a visit counter exists in the cache.
     *
     * @param  \Illuminate\Database\Eloquent\Model  $model
     * @param  string  $type
     * @param  string  $period
     * @return bool
     */
    public function has($model, string $type, string $period): bool
    {
        $visitCounterKey = $this->createKey($model, $type, $period);

        return $this->cache->has($visitCounterKey);
    }

    /**
     * Retrieve a visit counter from the cache.
     *
     * @param  \Illuminate\Database\Eloquent\Model  $model
     * @param  string  $type
     * @param  string  $period
     * @return mixed
     */
    public function get($model, string $type, string $period)
    {
        $visitCounterKey = $this->createKey($model, $type, $period);

        return $this->cache->get($visitCounterKey);
    }

    /**
     * Store a visit counter in the cache.
     *
     * @param  \Illuminate\Database\Eloquent\Model  $model
     * @param  string  $type
     * @param  string  $period
     * @param  int  $visitsCount
     * @param  \DateTimeInterface|\DateInterval|float|int  $minutes
     * @return void
     */
    public function put($model, string $type, string $period, int $visitsCount, $minutes = null)
    {
        $visitCounterKey = $this->createKey($model, $type, $period);
        $minutes = $minutes ?? config('eloquent-visitable.cache.events.cache_visits_count.default_lifetime_in_minutes', 60 * 10);

        $this->cache->put($visitCounterKey, $visitsCount, $minutes);
    }

    /**
     * Create a new key from the given data.
     *
     * @param  \Illuminate\Database\Eloquent\Model  $model
     * @param  string  $type
     * @param  string  $period
     * @return string
     */
    public function createKey($model, string $type, string $period)
    {
        $modelId = $model->getKey();
        $modelType = strtolower(str_replace('\\', '-', get_class($model)));

        return "{$this->cacheKey}.counters.{$modelType}.{$modelId}.{$type}.{$period}";
    }
}
