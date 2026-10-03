<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Querying\Cache;

use Carbon\Carbon;
use Carbon\CarbonInterface;
use Closure;
use Illuminate\Contracts\Cache\Repository as CacheRepository;

/**
 * Remembers values stamped with the versions they depend on, so bumping a
 * version through `CacheVersions` forgets every entry stamped with it without
 * knowing their keys. An entry and its versions are read in one round trip.
 *
 * @internal
 */
final readonly class VersionedCache
{
    public function __construct(
        private CacheRepository $cache,
        private CacheVersions $versions,
    ) {}

    /**
     * @template TValue
     *
     * @param  Closure(): TValue  $resolve
     * @return TValue
     */
    public function remember(string $key, ?string $type, int|string|null $id, CarbonInterface $until, Closure $resolve): mixed
    {
        $versionKeys = $this->versions->keys($type, $id);
        $read = $this->read([$key, ...$versionKeys]);
        $version = $this->versions->stamp($this->versions->resolve($versionKeys, $read), $versionKeys);
        $cached = $read[$key] ?? null;

        if ($this->isCurrent($cached, $version)) {
            /** @var TValue $value */
            $value = $cached['value'];

            return $value;
        }

        $value = $resolve();

        $this->cache->put($key, ['version' => $version, 'value' => $value], $until);

        return $value;
    }

    /**
     * Many entries of one type in one read, and the values the cache lacks
     * resolved together and written in one more.
     *
     * @template TValue
     *
     * @param  array<int|string, string>  $keys  cache keys by the key of the viewable they count
     * @param  Closure(non-empty-list<int|string>): array<int|string, TValue>  $resolve  given the viewable keys the cache lacks, a value for each
     * @return array<int|string, TValue>
     */
    public function rememberMany(string $type, array $keys, CarbonInterface $until, Closure $resolve): array
    {
        $planned = [];

        foreach ($keys as $id => $key) {
            $planned[$id] = ['key' => $key, 'versions' => $this->versions->keys($type, $id)];
        }

        $shared = array_values(array_unique(array_merge(...array_column($planned, 'versions'))));
        $read = $this->read([...array_values($keys), ...$shared]);
        $versions = $this->versions->resolve($shared, $read);
        $values = [];
        $pending = [];

        foreach ($planned as $id => ['key' => $key, 'versions' => $dependsOn]) {
            $version = $this->versions->stamp($versions, $dependsOn);
            $cached = $read[$key] ?? null;

            if ($this->isCurrent($cached, $version)) {
                /** @var TValue $value */
                $value = $cached['value'];
                $values[$id] = $value;
            } else {
                $pending[$id] = ['key' => $key, 'version' => $version];
            }
        }

        if ($pending === []) {
            return $values;
        }

        $fresh = $resolve(array_keys($pending));
        $entries = [];

        foreach ($pending as $id => $entry) {
            $entries[$entry['key']] = ['version' => $entry['version'], 'value' => $fresh[$id] ?? null];
        }

        // The PSR contract takes an interval, not a moment. One that lies in
        // the past makes the repository forget the keys, as put() does.
        $this->cache->setMultiple($entries, Carbon::now()->diff($until));

        return $values + $fresh;
    }

    /** @phpstan-assert-if-true array{version: string, value: mixed} $cached */
    private function isCurrent(mixed $cached, string $version): bool
    {
        return is_array($cached) && ($cached['version'] ?? null) === $version && array_key_exists('value', $cached);
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
