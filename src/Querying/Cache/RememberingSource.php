<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Querying\Cache;

use Carbon\CarbonInterface;
use Closure;
use CyrildeWit\EloquentViewable\Contracts\Viewable;
use CyrildeWit\EloquentViewable\Querying\Contracts\CountsByDimension;
use CyrildeWit\EloquentViewable\Querying\Contracts\RanksAlsoViewed;
use CyrildeWit\EloquentViewable\Querying\Contracts\RanksTrending;
use CyrildeWit\EloquentViewable\Querying\Contracts\ViewSource;
use CyrildeWit\EloquentViewable\Querying\Exceptions\UnsupportedBySource;
use CyrildeWit\EloquentViewable\Querying\Ranking\Decay;
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
final readonly class RememberingSource implements CountsByDimension, RanksAlsoViewed, RanksTrending, ViewSource
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
     * @return array<string, int>
     *
     * @throws UnsupportedBySource
     */
    public function countByDimension(Viewable $viewable, ViewsQuery $query, string $dimension): array
    {
        $source = $this->source;

        if (! $source instanceof CountsByDimension) {
            throw UnsupportedBySource::dimension($source);
        }

        return $this->remember(
            $viewable,
            $this->key($viewable)->make($query, grouping: "dimension:{$dimension}"),
            fn (): array => $source->countByDimension($viewable, $query, $dimension),
        );
    }

    /**
     * Each key is remembered on its own, so a set that overlaps an earlier
     * one only reads the keys the cache lacks. A key the source leaves out
     * is remembered as zero.
     *
     * @param  non-empty-list<int|string>  $keys
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
     * Remembered with the viewable, so forgetting its cache forgets what its
     * visitors also viewed. The minimum and the cap change the ranking, so
     * they are part of the key.
     *
     * @return list<array{type: string, id: int|string, count: int}>
     *
     * @throws UnsupportedBySource
     */
    public function alsoViewed(Viewable $viewable, ?Viewable $among, ViewsQuery $query, int $limit, int $minimum, ?int $maxVisitors): array
    {
        $source = $this->source;

        if (! $source instanceof RanksAlsoViewed) {
            throw UnsupportedBySource::alsoViewed($source);
        }

        return $this->remember(
            $viewable,
            $this->key($viewable)->make($query, grouping: "also-viewed:{$among?->getMorphClass()}:{$minimum}:{$maxVisitors}", limit: $limit),
            fn (): array => $source->alsoViewed($viewable, $among, $query, $limit, $minimum, $maxVisitors),
        );
    }

    /**
     * Remembered under the identity of the decay, which leaves out now, so
     * the ranking is served until the moment `remember()` names even as the
     * clock moves on. Another curve, step or period starts a fresh entry.
     *
     * @return list<array{type: string, id: int|string, count: int, score: float}>
     *
     * @throws UnsupportedBySource
     */
    public function trending(?Viewable $viewable, ViewsQuery $query, Decay $decay, int $limit): array
    {
        $source = $this->source;

        if (! $source instanceof RanksTrending) {
            throw UnsupportedBySource::trending($source);
        }

        return $this->remember(
            $viewable,
            $this->key($viewable)->make($query, grouping: "trending:{$decay->identity()}", limit: $limit),
            fn (): array => $source->trending($viewable, $query, $decay, $limit),
        );
    }

    /**
     * @template TValue of int|array<string, int>|list<array{type: string, id: int|string, count: int}>|list<array{type: string, id: int|string, count: int, score: float}>
     *
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
