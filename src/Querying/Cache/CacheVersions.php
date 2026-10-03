<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Querying\Cache;

use CyrildeWit\EloquentViewable\Contracts\Viewable;
use CyrildeWit\EloquentViewable\Support\Config;
use CyrildeWit\EloquentViewable\Support\ViewableKey;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Support\Str;

/**
 * Invalidates remembered counts without knowing their keys.
 *
 * Every entry is stored with the versions of the scopes its count reads
 * from, and served only while they are still current. Forgetting a scope
 * replaces its version with a fresh random token, so every entry stored under
 * the old one stops matching and is counted again on its next read. No store
 * needs tags and no key is ever listed.
 *
 *   all                    every entry
 *   ranking                rankings across every type
 *   models:{type}          every entry of a type, per model or as a whole
 *   type:{type}            the type as a whole: its total and its rankings
 *   model:{type}:{key}     one model
 *
 * A version that is missing, because it was never written or was evicted,
 * gets a fresh token on the next read. That only ever turns entries into
 * misses, never brings back one that was forgotten.
 *
 * Laravel's cache tags work much the same way, but only on the stores that
 * support them.
 *
 * @internal Reach it through `Views::forgetCache()` and `Views::flushCache()`.
 */
final readonly class CacheVersions
{
    private string $prefix;

    public function __construct(
        private CacheRepository $cache,
        Config $config,
    ) {
        // Read once: every count asks for its keys, a set for each model.
        $this->prefix = "{$config->cacheKey()}:version:";
    }

    /**
     * The keys of the versions an entry for this viewable depends on, from
     * the widest scope to the narrowest.
     *
     * @return list<string>
     */
    public function keys(?Viewable $viewable): array
    {
        if (! $viewable instanceof Viewable) {
            return [$this->key('all'), $this->key('ranking')];
        }

        $type = $viewable->getMorphClass();
        $key = ViewableKey::of($viewable);

        return [
            $this->key('all'),
            $this->key("models:{$type}"),
            $this->key($key === null ? "type:{$type}" : "model:{$type}:{$key}"),
        ];
    }

    /**
     * The versions under the keys, from what the caller read of them, so the
     * read can share a round trip with the entries. A missing one is started
     * with a fresh token.
     *
     * @param  list<string>  $keys
     * @param  array<string, mixed>  $read
     * @return array<string, string>
     */
    public function resolve(array $keys, array $read): array
    {
        $versions = [];

        foreach ($keys as $key) {
            $version = $read[$key] ?? null;

            if (! is_string($version)) {
                $version = $this->token();

                $this->cache->forever($key, $version);
            }

            $versions[$key] = $version;
        }

        return $versions;
    }

    /**
     * The version an entry carries: the versions under its keys, joined.
     *
     * @param  array<string, string>  $versions
     * @param  list<string>  $keys
     */
    public function stamp(array $versions, array $keys): string
    {
        return implode(':', array_intersect_key($versions, array_flip($keys)));
    }

    /**
     * Forgets every entry whose count can include the views of the viewable:
     * its own, the total and rankings of its type, and the rankings across
     * every type. A viewable without a key forgets its whole type.
     */
    public function forgetCache(Viewable $viewable): void
    {
        $type = $viewable->getMorphClass();
        $key = ViewableKey::of($viewable);

        $this->bump($key === null
            ? ["models:{$type}", 'ranking']
            : ["model:{$type}:{$key}", "type:{$type}", 'ranking']);
    }

    public function flushCache(): void
    {
        $this->bump(['all']);
    }

    /** @param  list<string>  $scopes */
    private function bump(array $scopes): void
    {
        foreach ($scopes as $scope) {
            $this->cache->forever($this->key($scope), $this->token());
        }
    }

    private function key(string $scope): string
    {
        return $this->prefix.$scope;
    }

    private function token(): string
    {
        return Str::random(16);
    }
}
