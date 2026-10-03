<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Querying\Cache;

use CyrildeWit\EloquentViewable\Contracts\Viewable;
use CyrildeWit\EloquentViewable\Support\Granularity;
use CyrildeWit\EloquentViewable\Support\ViewableKey;
use CyrildeWit\EloquentViewable\Support\ViewsQuery;

/**
 * Builds the cache key under which a viewable's view count is memoized.
 *
 * The key is a hybrid of two parts joined by a colon:
 *
 *   {prefix}:{morph class}:{key}:{digest}
 *
 * The head ({prefix}:{morph class}:{key}) is a human-readable, best-effort
 * label that keeps entries identifiable when inspecting the cache store. It is
 * not relied upon for uniqueness. The digest is a collision-safe hash over the
 * full identity of the count being cached, so two configurations only ever
 * share a key when they are genuinely the same count. The source driver is
 * part of that identity: switching `source.driver` starts fresh entries rather
 * than serving counts the old source produced.
 */
final readonly class CacheKey
{
    public function __construct(
        private Viewable $viewable,
        private string $prefix,
        private string $source,
    ) {}

    /**
     * The granularity identifies a count per interval and the grouping a
     * count per collection, so neither shares an entry with the plain total.
     */
    public function make(ViewsQuery $query, ?Granularity $granularity = null, ?string $grouping = null): string
    {
        return $this->head().':'.$this->digest($query, $granularity, $grouping);
    }

    private function head(): string
    {
        $key = ViewableKey::of($this->viewable);

        if ($key === null) {
            return "{$this->prefix}:type:{$this->viewable->getMorphClass()}";
        }

        return "{$this->prefix}:{$this->viewable->getMorphClass()}:{$key}";
    }

    private function digest(ViewsQuery $query, ?Granularity $granularity, ?string $grouping): string
    {
        $connection = $this->viewable->getConnection();

        return hash('xxh128', serialize([
            $this->source,
            $connection->getName(),
            $connection->getDatabaseName(),
            $this->viewable->getMorphClass(),
            $this->viewable->getKey(),
            $query->period?->cacheSignature(),
            $query->unique,
            $query->collection,
            $query->timezone?->getName(),
            $query->viewer?->getMorphClass(),
            $query->viewer?->getKey(),
            $granularity?->value,
            $grouping,
        ]));
    }
}
