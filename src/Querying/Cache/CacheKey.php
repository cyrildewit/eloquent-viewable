<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Querying\Cache;

use CyrildeWit\EloquentViewable\Contracts\FiltersViews;
use CyrildeWit\EloquentViewable\Support\DimensionFilter;
use CyrildeWit\EloquentViewable\Support\Granularity;
use CyrildeWit\EloquentViewable\Support\ViewsQuery;

/**
 * Builds the cache key under which a view count, a series or a ranking is
 * memoized.
 *
 * The key is a readable head and a digest, joined by a colon:
 *
 *   {prefix}:{morph class}:{key}:{digest}  one viewable
 *   {prefix}:type:{morph class}:{digest}   every viewable of a type
 *   {prefix}:top:{digest}                  a ranking across types
 *
 * The head only makes entries recognisable in the cache store, and nothing
 * relies on it being unique. The digest hashes the full identity of the
 * count, so two reads share a key only when they ask for the same count. The
 * source is part of that identity: its driver name, and whatever the source
 * reports through `IdentifiesSource`, such as the connection the database
 * source reads. Switching either starts fresh entries rather than serving
 * counts the old source produced.
 *
 * @internal
 */
final readonly class CacheKey
{
    public function __construct(
        private ?string $type,
        private int|string|null $key,
        private string $prefix,
        private string $source,
    ) {}

    /**
     * The granularity identifies a count per interval, the grouping a count
     * per collection and the limit a ranking, so none of them shares an
     * entry with the plain total or with one another.
     */
    public function make(ViewsQuery $query, ?Granularity $granularity = null, ?string $grouping = null, ?int $limit = null): string
    {
        return "{$this->head()}:{$this->digest($query, $granularity, $grouping, $limit)}";
    }

    private function head(): string
    {
        if ($this->type === null) {
            return "{$this->prefix}:top";
        }

        if ($this->key === null) {
            return "{$this->prefix}:type:{$this->type}";
        }

        return "{$this->prefix}:{$this->type}:{$this->key}";
    }

    private function digest(ViewsQuery $query, ?Granularity $granularity, ?string $grouping, ?int $limit): string
    {
        return hash('xxh128', serialize([
            $this->source,
            $this->type,
            $this->key,
            $query->period?->cacheSignature(),
            $query->unique,
            $query->collection,
            $query->timezone?->getName(),
            $query->viewer?->getMorphClass(),
            $query->viewer?->getKey(),
            $granularity?->value,
            $grouping,
            $limit,
            ...$this->filter($query),
            ...$this->dimensions($query),
        ]));
    }

    /**
     * The filter joins the identity only when it is set. An empty part for
     * an unfiltered count would change its digest, and with it the key of
     * every entry already cached without a filter.
     *
     * @return list<string>
     */
    private function filter(ViewsQuery $query): array
    {
        if (! $query->filter instanceof FiltersViews) {
            return [];
        }

        return [$query->filter->name()];
    }

    /**
     * The dimension filters join the identity only when there are any, in
     * one order whatever order they were added in.
     *
     * @return list<string>
     */
    private function dimensions(ViewsQuery $query): array
    {
        if ($query->dimensions === []) {
            return [];
        }

        $signatures = array_map(static fn (DimensionFilter $filter): string => $filter->signature(), $query->dimensions);

        sort($signatures, SORT_STRING);

        return ['dimensions', ...$signatures];
    }
}
