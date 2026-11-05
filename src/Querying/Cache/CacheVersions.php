<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Querying\Cache;

use CyrildeWit\EloquentViewable\Contracts\Viewable;
use CyrildeWit\EloquentViewable\Support\Config;
use CyrildeWit\EloquentViewable\Support\ViewableKey;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Support\Str;

/** @internal */
final readonly class CacheVersions
{
    private string $prefix;

    public function __construct(
        private CacheRepository $cache,
        Config $config,
    ) {
        $this->prefix = "{$config->cacheKey()}:version:";
    }

    /**
     * The version keys an entry is stamped with. Every entry depends on "all".
     * An entry of one viewable or of a whole type also depends on
     * "models:{type}", which forgetModel() bumps for the whole type. A
     * per-viewable entry adds "model:{type}:{key}" and a type-wide entry adds
     * "type:{type}", and forgetting one viewable bumps both, so the type-wide
     * totals it is part of are forgotten with it. A ranking across types
     * depends on "ranking", which every forget bumps.
     *
     * @return list<string>
     */
    public function keys(?string $type, int|string|null $key = null): array
    {
        if ($type === null) {
            return [$this->key('all'), $this->key('ranking')];
        }

        return [
            $this->key('all'),
            $this->key("models:{$type}"),
            $this->key($key === null ? "type:{$type}" : "model:{$type}:{$key}"),
        ];
    }

    /**
     * A version key the cache lacks, because it was never written or was
     * evicted, gets a new token that is stored forever. Entries stamped with
     * the lost token no longer match, so they are read as stale.
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
     * @param  array<string, string>  $versions
     * @param  list<string>  $keys
     */
    public function stamp(array $versions, array $keys): string
    {
        return implode(':', array_intersect_key($versions, array_flip($keys)));
    }

    public function forgetCache(Viewable $viewable): void
    {
        $this->forgetModel($viewable->getMorphClass(), ViewableKey::of($viewable));
    }

    /**
     * Forget the entries of a viewable known only by its morph type and key.
     * Without a key, forget every entry of the type.
     */
    public function forgetModel(string $type, int|string|null $key = null): void
    {
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
