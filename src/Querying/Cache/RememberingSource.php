<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Querying\Cache;

use Carbon\CarbonInterface;
use Closure;
use CyrildeWit\EloquentViewable\Contracts\Viewable;
use CyrildeWit\EloquentViewable\Querying\Contracts\ViewSource;
use CyrildeWit\EloquentViewable\Support\Granularity;
use CyrildeWit\EloquentViewable\Support\ViewableKey;
use CyrildeWit\EloquentViewable\Support\ViewsQuery;

/**
 * This source sits in front of another and serves what it read before from
 * the cache, until the moment `remember()` names. It keeps what the source
 * returns as it is, so the reader shapes cached and fresh results alike.
 * Entries are stored under the `querying.cache.key` prefix, and the identity
 * of the source keeps the entries of two sources apart.
 *
 * @internal
 */
final readonly class RememberingSource implements ViewSource
{
    public function __construct(
        private ViewSource $source,
        private VersionedCache $cache,
        private CarbonInterface $until,
        private string $prefix,
        private string $identity,
    ) {}

    public function count(Viewable $viewable, ViewsQuery $query): int
    {
        return $this->remember($viewable, $this->key($viewable)->make($query), fn (): int => $this->source->count($viewable, $query));
    }

    /** @return array<string, int> */
    public function countByInterval(Viewable $viewable, ViewsQuery $query, Granularity $granularity): array
    {
        return $this->remember(
            $viewable,
            $this->key($viewable)->make($query, $granularity),
            fn (): array => $this->source->countByInterval($viewable, $query, $granularity),
        );
    }

    /** @return array<string, int> */
    public function countByCollection(Viewable $viewable, ViewsQuery $query): array
    {
        return $this->remember(
            $viewable,
            $this->key($viewable)->make($query, grouping: 'collection'),
            fn (): array => $this->source->countByCollection($viewable, $query),
        );
    }

    /**
     * Each key is remembered on its own, so a set that overlaps an earlier
     * one only reads the keys the cache lacks. A key the source leaves out
     * is remembered as zero.
     *
     * @param  Viewable  $viewable
     * @param  non-empty-list<int|string>  $keys
     * @param  ViewsQuery  $query
     * @return array<int|string, int>
     */
    public function countMany(Viewable $viewable, array $keys, ViewsQuery $query): array
    {
        $type = $viewable->getMorphClass();
        $cacheKeys = [];

        foreach ($keys as $key) {
            $cacheKeys[$key] = new CacheKey($type, $key, $this->prefix, $this->identity)->make($query);
        }

        return $this->cache->rememberMany($type, $cacheKeys, $this->until, function (array $missing) use ($viewable, $query): array {
            $fetched = $this->source->countMany($viewable, $missing, $query);
            $counts = [];

            foreach ($missing as $key) {
                $counts[$key] = $fetched[$key] ?? 0;
            }

            return $counts;
        });
    }

    /** @return list<array{type: string, id: int|string, count: int}> */
    public function top(?Viewable $viewable, ViewsQuery $query, int $limit): array
    {
        return $this->remember(
            $viewable,
            $this->key($viewable)->make($query, limit: $limit),
            fn (): array => $this->source->top($viewable, $query, $limit),
        );
    }

    /**
     * @template TValue of int|array<string, int>|list<array{type: string, id: int|string, count: int}>
     *
     * @param  ?Viewable  $viewable
     * @param  string  $key
     * @param  Closure(): TValue  $resolve
     * @return TValue
     */
    private function remember(?Viewable $viewable, string $key, Closure $resolve): int|array
    {
        return $this->cache->remember(
            $key,
            $viewable?->getMorphClass(),
            $viewable instanceof Viewable ? ViewableKey::of($viewable) : null,
            $this->until,
            $resolve,
        );
    }

    private function key(?Viewable $viewable): CacheKey
    {
        return new CacheKey(
            $viewable?->getMorphClass(),
            $viewable instanceof Viewable ? ViewableKey::of($viewable) : null,
            $this->prefix,
            $this->identity,
        );
    }
}
