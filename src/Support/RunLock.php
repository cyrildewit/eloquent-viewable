<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Support;

use Closure;
use CyrildeWit\EloquentViewable\Exceptions\InvalidConfiguration;
use CyrildeWit\EloquentViewable\Exceptions\LockUnavailable;
use Illuminate\Contracts\Cache\Factory as CacheFactory;
use Illuminate\Contracts\Cache\LockProvider;

/**
 * This lock is shared by every command that rewrites the views or rollup
 * tables, so two servers, or two commands on one, never run at once.
 *
 * @internal
 */
final readonly class RunLock
{
    /**
     * The lock lasts long enough for a large first run, and a lock left by a
     * process that died is released after it.
     */
    private const int Seconds = 3600;

    public function __construct(
        private Config $config,
        private CacheFactory $cache,
    ) {}

    /**
     * It returns what the callback returns, or null when another run holds
     * the lock.
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

        $result = $store->lock("{$this->config->cacheKey()}:maintenance", self::Seconds)->get($callback);

        return is_int($result) ? $result : null;
    }
}
