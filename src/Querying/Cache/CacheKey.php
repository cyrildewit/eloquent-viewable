<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Querying\Cache;

use CyrildeWit\EloquentViewable\Contracts\FiltersViews;
use CyrildeWit\EloquentViewable\Support\Granularity;
use CyrildeWit\EloquentViewable\Support\ViewsQuery;

/**
 * Builds the cache key under which a view count, a series or a ranking is
 * memoized.
 *
 * The key is a hybrid of two parts joined by a colon:
 *
 *   {prefix}:{morph class}:{key}:{digest}
 *
 * The head ({prefix}:{morph class}:{key}) is a human-readable, best-effort
 * label that keeps entries identifiable when inspecting the cache store. It is
 * not relied upon for uniqueness. The digest is a collision-safe hash over the
 * full identity of the count being cached, so two configurations only ever
 * share a key when they are genuinely the same count. The source is part of
 * that identity: its driver name, and whatever the source reports through
 * `IdentifiesSource`, such as the connection the database source reads, so
 * switching either starts fresh entries rather than serving counts the old
 * source produced.
 *
 * A type without a key stands for every viewable of the type, and no type at
 * all for every type, which only a ranking asks for.
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
        ]));
    }

    /**
     * The filter joins the identity only when it is set, so the keys of
     * unfiltered counts stay as they were.
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
}
