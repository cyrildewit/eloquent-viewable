<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Support;

use Carbon\CarbonImmutable;
use Closure;
use CyrildeWit\EloquentViewable\Exceptions\InvalidConfiguration;
use CyrildeWit\EloquentViewable\Exceptions\LockUnavailable;
use Illuminate\Cache\Lock;
use Illuminate\Contracts\Cache\Factory as CacheFactory;
use Illuminate\Contracts\Cache\Lock as LockContract;
use Illuminate\Contracts\Cache\LockProvider;
use RuntimeException;

/** @internal */
final readonly class RunLock
{
    /**
     * The lock expires after an hour, so a lock left by a process that died
     * does not block every run after it.
     */
    private const int Seconds = 3600;

    /**
     * A run that is still working pushes the expiry back at most this often,
     * so a run longer than an hour keeps its lock.
     */
    private const int RefreshEvery = 60;

    public function __construct(
        private Config $config,
        private CacheFactory $cache,
    ) {}

    /**
     * The callback receives the deadline, with a heartbeat that keeps the lock
     * alive for as long as the run keeps asking it. It returns null when
     * another run holds the lock.
     *
     * @template TResult of int|object
     *
     * @param  Closure(Deadline): TResult  $callback
     * @return ?TResult
     *
     * @throws InvalidConfiguration
     * @throws LockUnavailable
     */
    public function run(Closure $callback, ?Deadline $deadline = null): int|object|null
    {
        $name = $this->config->cacheStore();
        $store = $this->cache->store($name)->getStore();

        if (! $store instanceof LockProvider) {
            throw LockUnavailable::storeCannotLock($name ?? 'default');
        }

        $lock = $store->lock("{$this->config->cacheKey()}:maintenance", self::Seconds);
        $deadline = ($deadline ?? Deadline::none())->withHeartbeat($this->heartbeat($lock));

        $acquired = false;

        $result = $lock->get(function () use ($callback, $deadline, &$acquired): int|object {
            $acquired = true;

            return $callback($deadline);
        });

        /** @var TResult $result */
        return $acquired ? $result : null;
    }

    /** @return Closure(): void */
    private function heartbeat(LockContract $lock): Closure
    {
        $refreshed = CarbonImmutable::now();

        return function () use ($lock, &$refreshed): void {
            $now = CarbonImmutable::now();

            if ($refreshed->diffInSeconds($now) < self::RefreshEvery) {
                return;
            }

            $refreshed = $now;

            $this->refresh($lock);
        };
    }

    /**
     * A driver that cannot refresh keeps the hour it was given.
     */
    private function refresh(LockContract $lock): void
    {
        if (! $lock instanceof Lock) {
            return;
        }

        try {
            $lock->refresh(self::Seconds);
        } catch (RuntimeException) {
            return;
        }
    }
}
