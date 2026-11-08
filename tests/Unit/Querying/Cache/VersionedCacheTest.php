<?php

declare(strict_types=1);

use Carbon\Carbon;
use CyrildeWit\EloquentViewable\Querying\Cache\CacheVersions;
use CyrildeWit\EloquentViewable\Querying\Cache\DeferredRefresh;
use CyrildeWit\EloquentViewable\Querying\Cache\Lifetime;
use CyrildeWit\EloquentViewable\Querying\Cache\VersionedCache;
use CyrildeWit\EloquentViewable\Querying\Exceptions\InvalidLifetime;
use CyrildeWit\EloquentViewable\Support\Config;
use Illuminate\Cache\ArrayStore;
use Illuminate\Cache\Repository as CacheRepository;
use Illuminate\Config\Repository;
use Illuminate\Container\Container;
use Illuminate\Contracts\Cache\Store;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Support\Defer\DeferredCallbackCollection;

function versionedCache(CacheRepository $cache, ?Container $container = null): VersionedCache
{
    return new VersionedCache($cache, cacheVersions($cache), new DeferredRefresh($container ?? test()->container));
}

function cacheVersions(CacheRepository $cache): CacheVersions
{
    return new CacheVersions($cache, new Config(new Repository(['eloquent-viewable' => ['querying' => ['cache' => ['key' => 'views']]]])));
}

function storeWithoutLocks(): Store
{
    return new readonly class implements Store
    {
        private ArrayStore $store;

        public function __construct()
        {
            $this->store = new ArrayStore;
        }

        public function get($key): mixed
        {
            return $this->store->get($key);
        }

        public function many(array $keys): array
        {
            return $this->store->many($keys);
        }

        public function put($key, $value, $seconds): bool
        {
            return $this->store->put($key, $value, $seconds);
        }

        public function putMany(array $values, $seconds): bool
        {
            return $this->store->putMany($values, $seconds);
        }

        public function increment($key, $value = 1): int|bool
        {
            return $this->store->increment($key, $value);
        }

        public function decrement($key, $value = 1): int|bool
        {
            return $this->store->decrement($key, $value);
        }

        public function forever($key, $value): bool
        {
            return $this->store->forever($key, $value);
        }

        public function touch($key, $seconds): bool
        {
            return $this->store->touch($key, $seconds);
        }

        public function forget($key): bool
        {
            return $this->store->forget($key);
        }

        public function flush(): bool
        {
            return $this->store->flush();
        }

        public function getPrefix(): string
        {
            return '';
        }
    };
}

/** Returns each value in turn and counts the calls. */
function countingResolver(int ...$values): object
{
    return new class($values)
    {
        public int $calls = 0;

        /** @param  list<int>  $values */
        public function __construct(private array $values) {}

        public function __invoke(): int
        {
            return $this->values[$this->calls++];
        }
    };
}

beforeEach(function (): void {
    Carbon::setTestNow('2026-09-10 12:00:00');

    $this->deferred = new DeferredCallbackCollection;
    $this->container = new Container;
    $this->container->instance(DeferredCallbackCollection::class, $this->deferred);

    $this->cache = new CacheRepository(new ArrayStore);
    $this->versioned = versionedCache($this->cache);

    $this->lifetime = fn (): Lifetime => new Lifetime(Carbon::now()->addMinutes(10), Carbon::now()->addHour());
});

describe('lifetime', function (): void {
    it('refuses a stale window that does not end after the lifetime', function (): void {
        new Lifetime(Carbon::now()->addMinutes(10), Carbon::now()->addMinutes(10));
    })->throws(InvalidLifetime::class, 'must end after the lifetime');

    it('expires at the end of the stale window, or at the lifetime without one', function (): void {
        $fresh = Carbon::now()->addMinutes(10);
        $stale = Carbon::now()->addHour();

        expect(new Lifetime($fresh, $stale)->expiresAt())->toBe($stale)
            ->and(new Lifetime($fresh, $stale)->servesStale())->toBeTrue()
            ->and(new Lifetime($fresh)->expiresAt())->toBe($fresh)
            ->and(new Lifetime($fresh)->servesStale())->toBeFalse();
    });
});

describe('remember', function (): void {
    it('serves a fresh entry without counting again', function (): void {
        $resolve = countingResolver(3);

        $this->versioned->remember('views:a', 'posts', 7, ($this->lifetime)(), $resolve(...));

        expect($this->versioned->remember('views:a', 'posts', 7, ($this->lifetime)(), $resolve(...)))->toBe(3)
            ->and($resolve->calls)->toBe(1)
            ->and($this->deferred)->toBeEmpty();
    });

    it('counts again before answering once the lifetime passed, without a stale window', function (): void {
        $resolve = countingResolver(3, 4);
        $lifetime = fn (): Lifetime => new Lifetime(Carbon::now()->addMinutes(10));

        $this->versioned->remember('views:a', 'posts', 7, $lifetime(), $resolve(...));

        Carbon::setTestNow(Carbon::now()->addMinutes(11));

        expect($this->versioned->remember('views:a', 'posts', 7, $lifetime(), $resolve(...)))->toBe(4)
            ->and($this->deferred)->toBeEmpty();
    });

    it('serves a stale entry at once and refreshes it after the response', function (): void {
        $resolve = countingResolver(3, 4);

        $this->versioned->remember('views:a', 'posts', 7, ($this->lifetime)(), $resolve(...));

        Carbon::setTestNow(Carbon::now()->addMinutes(11));

        expect($this->versioned->remember('views:a', 'posts', 7, ($this->lifetime)(), $resolve(...)))->toBe(3)
            ->and($resolve->calls)->toBe(1);

        $this->deferred->invoke();

        expect($resolve->calls)->toBe(2)
            ->and($this->versioned->remember('views:a', 'posts', 7, ($this->lifetime)(), $resolve(...)))->toBe(4)
            ->and($resolve->calls)->toBe(2);
    });

    it('counts a stale entry again for a read without a stale window', function (): void {
        $resolve = countingResolver(3, 4);

        $this->versioned->remember('views:a', 'posts', 7, ($this->lifetime)(), $resolve(...));

        Carbon::setTestNow(Carbon::now()->addMinutes(11));

        expect($this->versioned->remember('views:a', 'posts', 7, new Lifetime(Carbon::now()->addMinutes(10)), $resolve(...)))->toBe(4)
            ->and($this->deferred)->toBeEmpty();
    });

    it('forgets the entry once the stale window ends', function (): void {
        $resolve = countingResolver(3, 4);

        $this->versioned->remember('views:a', 'posts', 7, ($this->lifetime)(), $resolve(...));

        Carbon::setTestNow(Carbon::now()->addHour()->addSecond());

        expect($this->versioned->remember('views:a', 'posts', 7, ($this->lifetime)(), $resolve(...)))->toBe(4)
            ->and($this->deferred)->toBeEmpty();
    });

    it('never serves an entry stale once it is forgotten', function (Closure $forget): void {
        $resolve = countingResolver(3, 4);

        $this->versioned->remember('views:a', 'posts', 7, ($this->lifetime)(), $resolve(...));

        Carbon::setTestNow(Carbon::now()->addMinutes(11));
        $forget(cacheVersions($this->cache));

        expect($this->versioned->remember('views:a', 'posts', 7, ($this->lifetime)(), $resolve(...)))->toBe(4)
            ->and($this->deferred)->toBeEmpty();
    })->with([
        'the viewable' => [fn (CacheVersions $versions) => $versions->forgetModel('posts', 7)],
        'the type' => [fn (CacheVersions $versions) => $versions->forgetModel('posts')],
        'everything' => [fn (CacheVersions $versions) => $versions->flushCache()],
    ]);

    it('serves an entry marked stale while it refreshes it, inside its lifetime', function (): void {
        $resolve = countingResolver(3, 4);

        $this->versioned->remember('views:a', 'posts', 7, ($this->lifetime)(), $resolve(...));
        cacheVersions($this->cache)->markStale();

        expect($this->versioned->remember('views:a', 'posts', 7, ($this->lifetime)(), $resolve(...)))->toBe(3);

        $this->deferred->invoke();

        expect($this->versioned->remember('views:a', 'posts', 7, ($this->lifetime)(), $resolve(...)))->toBe(4)
            ->and($resolve->calls)->toBe(2);
    });

    it('counts an entry marked stale again before answering, without a stale window', function (): void {
        $resolve = countingResolver(3, 4);
        $lifetime = new Lifetime(Carbon::now()->addMinutes(10));

        $this->versioned->remember('views:a', 'posts', 7, $lifetime, $resolve(...));
        cacheVersions($this->cache)->markStale();

        expect($this->versioned->remember('views:a', 'posts', 7, $lifetime, $resolve(...)))->toBe(4)
            ->and($this->deferred)->toBeEmpty();
    });

    it('refreshes an entry once however often a request reads it stale', function (): void {
        $resolve = countingResolver(3, 4, 5);

        $this->versioned->remember('views:a', 'posts', 7, ($this->lifetime)(), $resolve(...));

        Carbon::setTestNow(Carbon::now()->addMinutes(11));

        $this->versioned->remember('views:a', 'posts', 7, ($this->lifetime)(), $resolve(...));
        $this->versioned->remember('views:a', 'posts', 7, ($this->lifetime)(), $resolve(...));
        $this->deferred->invoke();

        expect($resolve->calls)->toBe(2);
    });

    it('leaves the refresh to the process that holds the lock', function (): void {
        $resolve = countingResolver(3, 4);

        $this->versioned->remember('views:a', 'posts', 7, ($this->lifetime)(), $resolve(...));

        Carbon::setTestNow(Carbon::now()->addMinutes(11));

        $this->versioned->remember('views:a', 'posts', 7, ($this->lifetime)(), $resolve(...));

        $lock = $this->cache->getStore()->lock('views:a:refresh', 300);
        $lock->get();

        $this->deferred->invoke();

        expect($resolve->calls)->toBe(1);
    });

    it('skips a refresh another process finished first', function (): void {
        $resolve = countingResolver(3, 4);
        $other = countingResolver(5);

        $this->versioned->remember('views:a', 'posts', 7, ($this->lifetime)(), $resolve(...));

        Carbon::setTestNow(Carbon::now()->addMinutes(11));

        $this->versioned->remember('views:a', 'posts', 7, ($this->lifetime)(), $resolve(...));

        // Another process read the entry stale as well and refreshed it first.
        $elsewhere = new DeferredCallbackCollection;
        $container = new Container;
        $container->instance(DeferredCallbackCollection::class, $elsewhere);
        versionedCache($this->cache, $container)->remember('views:a', 'posts', 7, ($this->lifetime)(), $other(...));
        $elsewhere->invoke();

        $this->deferred->invoke();

        expect($resolve->calls)->toBe(1)
            ->and($this->versioned->remember('views:a', 'posts', 7, ($this->lifetime)(), $resolve(...)))->toBe(5);
    });

    it('stamps a refresh with the versions before it counted', function (): void {
        $versions = cacheVersions($this->cache);
        $resolve = countingResolver(3, 4, 5);

        $this->versioned->remember('views:a', 'posts', 7, ($this->lifetime)(), $resolve(...));

        Carbon::setTestNow(Carbon::now()->addMinutes(11));

        $this->versioned->remember('views:a', 'posts', 7, ($this->lifetime)(), function () use ($resolve, $versions): int {
            // The viewable is forgotten while the refresh counts.
            $versions->forgetModel('posts', 7);

            return $resolve();
        });

        $this->deferred->invoke();

        expect($this->versioned->remember('views:a', 'posts', 7, ($this->lifetime)(), $resolve(...)))->toBe(5);
    });

    it('refreshes without a lock on a store that cannot lock', function (): void {
        $cache = new CacheRepository(storeWithoutLocks());
        $versioned = versionedCache($cache);
        $resolve = countingResolver(3, 4);

        $versioned->remember('views:a', 'posts', 7, ($this->lifetime)(), $resolve(...));

        Carbon::setTestNow(Carbon::now()->addMinutes(11));

        expect($versioned->remember('views:a', 'posts', 7, ($this->lifetime)(), $resolve(...)))->toBe(3);

        $this->deferred->invoke();

        expect($versioned->remember('views:a', 'posts', 7, ($this->lifetime)(), $resolve(...)))->toBe(4);
    });

    it('keeps the stale entry when its refresh throws', function (): void {
        // Laravel reports what a deferred callback throws through the global container.
        $handler = Mockery::mock(ExceptionHandler::class);
        $handler->expects('report')->once()->with(Mockery::on(fn (Throwable $exception): bool => $exception->getMessage() === 'database gone'));
        $this->container->instance(ExceptionHandler::class, $handler);
        $previous = Container::getInstance();
        Container::setInstance($this->container);

        try {
            $this->versioned->remember('views:a', 'posts', 7, ($this->lifetime)(), fn (): int => 3);

            Carbon::setTestNow(Carbon::now()->addMinutes(11));

            $this->versioned->remember('views:a', 'posts', 7, ($this->lifetime)(), fn (): int => throw new RuntimeException('database gone'));
            $this->deferred->invoke();

            expect($this->versioned->remember('views:a', 'posts', 7, ($this->lifetime)(), fn (): int => 4))->toBe(3);
        } finally {
            Container::setInstance($previous);
        }
    });

    it('refreshes at once where nothing runs deferred callbacks', function (): void {
        $versioned = versionedCache($this->cache, new Container);
        $resolve = countingResolver(3, 4);

        $versioned->remember('views:a', 'posts', 7, ($this->lifetime)(), $resolve(...));

        Carbon::setTestNow(Carbon::now()->addMinutes(11));

        expect($versioned->remember('views:a', 'posts', 7, ($this->lifetime)(), $resolve(...)))->toBe(3)
            ->and($resolve->calls)->toBe(2);
    });
});

describe('remember many', function (): void {
    it('serves the stale entries and refreshes them together in one call', function (): void {
        $calls = [];
        $resolve = function (array $missing) use (&$calls): array {
            $calls[] = $missing;

            return array_fill_keys($missing, count($calls));
        };

        $this->versioned->rememberMany('posts', [7 => 'views:7', 8 => 'views:8'], ($this->lifetime)(), $resolve);

        Carbon::setTestNow(Carbon::now()->addMinutes(11));

        expect($this->versioned->rememberMany('posts', [7 => 'views:7', 8 => 'views:8', 9 => 'views:9'], ($this->lifetime)(), $resolve))
            ->toEqualCanonicalizing([7 => 1, 8 => 1, 9 => 2]);

        $this->deferred->invoke();

        expect($calls)->toBe([[7, 8], [9], [7, 8]])
            ->and($this->versioned->rememberMany('posts', [7 => 'views:7', 8 => 'views:8', 9 => 'views:9'], ($this->lifetime)(), $resolve))
            ->toEqualCanonicalizing([7 => 3, 8 => 3, 9 => 2]);
    });

    it('counts the stale entries again before answering, without a stale window', function (): void {
        $calls = [];
        $resolve = function (array $missing) use (&$calls): array {
            $calls[] = $missing;

            return array_fill_keys($missing, count($calls));
        };
        $lifetime = fn (): Lifetime => new Lifetime(Carbon::now()->addMinutes(10));

        $this->versioned->rememberMany('posts', [7 => 'views:7'], $lifetime(), $resolve);
        cacheVersions($this->cache)->markStale();

        expect($this->versioned->rememberMany('posts', [7 => 'views:7'], $lifetime(), $resolve))->toBe([7 => 2])
            ->and($this->deferred)->toBeEmpty();
    });

    it('skips a refresh of entries another process finished first', function (): void {
        $calls = 0;
        $resolve = function (array $missing) use (&$calls): array {
            $calls++;

            return array_fill_keys($missing, $calls);
        };

        $this->versioned->rememberMany('posts', [7 => 'views:7'], ($this->lifetime)(), $resolve);

        Carbon::setTestNow(Carbon::now()->addMinutes(11));

        $this->versioned->rememberMany('posts', [7 => 'views:7'], ($this->lifetime)(), $resolve);
        $this->versioned->rememberMany('posts', [7 => 'views:7'], new Lifetime(Carbon::now()->addMinutes(10)), $resolve);
        $this->deferred->invoke();

        expect($calls)->toBe(2);
    });
});
