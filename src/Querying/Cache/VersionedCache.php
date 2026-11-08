<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Querying\Cache;

use Carbon\Carbon;
use Closure;
use Illuminate\Contracts\Cache\LockProvider;
use Illuminate\Contracts\Cache\Repository as CacheRepository;

/**
 * This cache remembers values stamped with the versions they depend on, so
 * bumping a version through `CacheVersions` forgets every entry stamped with
 * it without knowing their keys. An entry and its versions are read in one
 * round trip.
 *
 * An entry is fresh until its lifetime and is kept until its stale window
 * ends. A read with a stale window serves an entry that is past its lifetime,
 * or that `CacheVersions::markStale()` marked, and refreshes it after the
 * response under a lock, so one process counts again while the others keep
 * serving the old answer. Any other read counts again before it answers.
 *
 * @phpstan-type Stamp array{version: string, refresh: string}
 * @phpstan-type Entry array{version: string, refresh: string, fresh: int, value: mixed}
 *
 * @internal
 */
final readonly class VersionedCache
{
    /**
     * A refresh holds its lock at most this long, so a process that dies while
     * refreshing holds up the next refresh only this long. A refresh that runs
     * longer can be joined by a second one, which only repeats the work.
     */
    private const int RefreshLockSeconds = 300;

    public function __construct(
        private CacheRepository $cache,
        private CacheVersions $versions,
        private DeferredRefresh $refresh = new DeferredRefresh,
    ) {}

    /**
     * @template TValue
     *
     * @param  Closure(): TValue  $resolve
     * @return TValue
     */
    public function remember(string $key, ?string $type, int|string|null $id, Lifetime $lifetime, Closure $resolve): mixed
    {
        $dependsOn = $this->versions->keys($type, $id);
        ['stamp' => $stamp, 'cached' => $cached] = $this->inspectOne($key, $dependsOn);
        $fresh = $this->isFresh($cached, $stamp);

        if ($this->isCurrent($cached, $stamp) && ($fresh || $lifetime->servesStale())) {
            if (! $fresh) {
                $this->refreshLater("{$key}:refresh", function () use ($key, $dependsOn, $lifetime, $resolve): void {
                    ['stamp' => $stamp, 'cached' => $cached] = $this->inspectOne($key, $dependsOn);

                    if ($this->isFresh($cached, $stamp)) {
                        return;
                    }

                    $this->cache->put($key, $this->entry($stamp, $lifetime, $resolve()), $lifetime->expiresAt());
                });
            }

            /** @var TValue $value */
            $value = $cached['value'];

            return $value;
        }

        $value = $resolve();

        $this->cache->put($key, $this->entry($stamp, $lifetime, $value), $lifetime->expiresAt());

        return $value;
    }

    /**
     * Read the entries of many viewables of one type in one round trip.
     * Resolve the missing values in one call and write them in one more round
     * trip. $keys maps each viewable key to its cache key. $resolve receives
     * the viewable keys the cache lacks and returns a value for each. The
     * stale entries a read serves are refreshed together, in one call.
     *
     * @template TValue
     *
     * @param  array<int|string, string>  $keys
     * @param  Closure(non-empty-list<int|string>): array<int|string, TValue>  $resolve
     * @return array<int|string, TValue>
     */
    public function rememberMany(string $type, array $keys, Lifetime $lifetime, Closure $resolve): array
    {
        $plan = [];

        foreach ($keys as $id => $key) {
            $plan[$id] = ['key' => $key, 'versions' => $this->versions->keys($type, $id)];
        }

        $values = [];
        $pending = [];
        $stale = [];

        foreach ($this->inspect($plan) as $id => $inspected) {
            ['stamp' => $stamp, 'cached' => $cached] = $inspected;
            $fresh = $this->isFresh($cached, $stamp);

            if (! $this->isCurrent($cached, $stamp) || (! $fresh && ! $lifetime->servesStale())) {
                $pending[$id] = $inspected;

                continue;
            }

            if (! $fresh) {
                $stale[$id] = ['key' => $inspected['key'], 'versions' => $inspected['versions']];
            }

            /** @var TValue $value */
            $value = $cached['value'];
            $values[$id] = $value;
        }

        if ($stale !== []) {
            $this->refreshLater($this->lockName(array_column($stale, 'key')), function () use ($stale, $lifetime, $resolve): void {
                $due = array_filter($this->inspect($stale), fn (array $inspected): bool => ! $this->isFresh($inspected['cached'], $inspected['stamp']));

                if ($due !== []) {
                    $this->writeMany($due, $resolve(array_keys($due)), $lifetime);
                }
            });
        }

        if ($pending === []) {
            return $values;
        }

        $fresh = $resolve(array_keys($pending));

        $this->writeMany($pending, $fresh, $lifetime);

        return $values + $fresh;
    }

    /**
     * @template TId of int|string
     *
     * @param  array<TId, array{key: string, versions: list<string>}>  $plan
     * @return array<TId, array{key: string, versions: list<string>, stamp: Stamp, cached: mixed}>
     */
    private function inspect(array $plan): array
    {
        $shared = array_values(array_unique(array_merge(...array_column($plan, 'versions'))));
        [$read, $versions] = $this->readStamped(array_column($plan, 'key'), $shared);
        $inspected = [];

        foreach ($plan as $id => ['key' => $key, 'versions' => $dependsOn]) {
            $inspected[$id] = ['key' => $key, 'versions' => $dependsOn, 'stamp' => $this->stamp($versions, $dependsOn), 'cached' => $read[$key] ?? null];
        }

        return $inspected;
    }

    /**
     * @param  list<string>  $dependsOn
     * @return array{stamp: Stamp, cached: mixed}
     */
    private function inspectOne(string $key, array $dependsOn): array
    {
        [$read, $versions] = $this->readStamped([$key], $dependsOn);

        return ['stamp' => $this->stamp($versions, $dependsOn), 'cached' => $read[$key] ?? null];
    }

    /**
     * The entries and their versions are read in one round trip, because
     * every remembered read pays for it.
     *
     * @param  list<string>  $keys
     * @param  list<string>  $versionKeys
     * @return array{array<string, mixed>, array<string, string>}
     */
    private function readStamped(array $keys, array $versionKeys): array
    {
        $versionKeys = [...$versionKeys, $this->versions->refreshKey()];
        $read = $this->read([...$keys, ...$versionKeys]);

        return [$read, $this->versions->resolve($versionKeys, $read)];
    }

    /**
     * @param  array<string, string>  $versions
     * @param  list<string>  $dependsOn
     * @return Stamp
     */
    private function stamp(array $versions, array $dependsOn): array
    {
        return [
            'version' => $this->versions->stamp($versions, $dependsOn),
            'refresh' => $this->versions->stamp($versions, [$this->versions->refreshKey()]),
        ];
    }

    /** @param  Closure(): void  $refresh */
    private function refreshLater(string $name, Closure $refresh): void
    {
        $this->refresh->schedule($name, function () use ($name, $refresh): void {
            $store = $this->cache->getStore();

            if (! $store instanceof LockProvider) {
                $refresh();

                return;
            }

            $store->lock($name, self::RefreshLockSeconds)->get($refresh);
        });
    }

    /**
     * One name per set of entries, so two reads of the same entries share the
     * lock and the deferred refresh.
     *
     * @param  non-empty-list<string>  $keys
     */
    private function lockName(array $keys): string
    {
        sort($keys);

        return "{$keys[0]}:refresh:".hash('xxh128', implode("\0", $keys));
    }

    /**
     * @param  array<int|string, array{key: string, versions: list<string>, stamp: Stamp, cached: mixed}>  $entries
     * @param  array<int|string, mixed>  $values
     */
    private function writeMany(array $entries, array $values, Lifetime $lifetime): void
    {
        $write = [];

        foreach ($entries as $id => $entry) {
            $write[$entry['key']] = $this->entry($entry['stamp'], $lifetime, $values[$id] ?? null);
        }

        $this->cache->setMultiple($write, Carbon::now()->diff($lifetime->expiresAt()));
    }

    /**
     * @param  Stamp  $stamp
     * @return Entry
     */
    private function entry(array $stamp, Lifetime $lifetime, mixed $value): array
    {
        return [...$stamp, 'fresh' => $lifetime->freshUntil->getTimestamp(), 'value' => $value];
    }

    /**
     * A current entry can still be stale. See isFresh().
     *
     * @param  Stamp  $stamp
     *
     * @phpstan-assert-if-true array{version: string, value: mixed, refresh?: mixed, fresh?: mixed} $cached
     */
    private function isCurrent(mixed $cached, array $stamp): bool
    {
        return is_array($cached) && ($cached['version'] ?? null) === $stamp['version'] && array_key_exists('value', $cached);
    }

    /** @param  Stamp  $stamp */
    private function isFresh(mixed $cached, array $stamp): bool
    {
        return $this->isCurrent($cached, $stamp)
            && ($cached['refresh'] ?? null) === $stamp['refresh']
            && is_int($cached['fresh'] ?? null)
            && $cached['fresh'] > Carbon::now()->getTimestamp();
    }

    /**
     * @param  list<string>  $keys
     * @return array<string, mixed>
     */
    private function read(array $keys): array
    {
        $read = [];

        foreach ($this->cache->getMultiple($keys) as $key => $value) {
            $read[$key] = $value;
        }

        return $read;
    }
}
