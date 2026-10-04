<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Support;

use Closure;
use CyrildeWit\EloquentViewable\Exceptions\InvalidConfiguration;
use CyrildeWit\EloquentViewable\Exceptions\LockUnavailable;
use Illuminate\Contracts\Cache\Factory as CacheFactory;
use Illuminate\Contracts\Cache\LockProvider;

/**
 * One atomic lock for every command that rewrites the views or rollup
 * tables, so two servers, or two commands on one, never run at once.
 *
 * @internal
 */
final readonly class RunLock
{
    /**
     * Long enough for a large first run. A lock left by a process that died
     * is released after it.
     */
    private const int SECONDS = 3600;

    public function __construct(
        private Config $config,
        private CacheFactory $cache,
    ) {}

    /**
     * Null when another run holds the lock.
     *
     * @param  Closure(): int  $callback
     *
     * @throws InvalidConfiguration
     * @throws LockUnavailable
     */
    public function run(Closure $callback): ?int
    {
        $name = $this->config->cacheStore();
        $store = $this->cache->store($name)->getStore();

        if (! $store instanceof LockProvider) {
            throw LockUnavailable::storeCannotLock($name ?? 'default');
        }

        $result = $store->lock("{$this->config->cacheKey()}:maintenance", self::SECONDS)->get($callback);

        return is_int($result) ? $result : null;
    }
}
