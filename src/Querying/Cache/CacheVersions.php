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
     * The versions an entry depends on. A type without a key stands for every
     * viewable of the type, and no type at all for every type.
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
