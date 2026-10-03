<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Querying\Cache;

use CyrildeWit\EloquentViewable\Contracts\Viewable;
use CyrildeWit\EloquentViewable\Support\Granularity;
use CyrildeWit\EloquentViewable\Support\ViewableKey;
use CyrildeWit\EloquentViewable\Support\ViewsQuery;
use Illuminate\Database\Connection;

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
 * share a key when they are genuinely the same count. The source driver is
 * part of that identity: switching `source.driver` starts fresh entries rather
 * than serving counts the old source produced. So is the connection the views
 * are read from, which keeps two databases behind one cache store apart.
 *
 * A viewable without a key stands for its type, and no viewable at all for
 * every type, which only a ranking asks for.
 */
final readonly class CacheKey
{
    public function __construct(
        private ?Viewable $viewable,
        private Connection $connection,
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
        return $this->head().':'.$this->digest($query, $granularity, $grouping, $limit);
    }

    private function head(): string
    {
        if (! $this->viewable instanceof Viewable) {
            return "{$this->prefix}:top";
        }

        $key = ViewableKey::of($this->viewable);

        if ($key === null) {
            return "{$this->prefix}:type:{$this->viewable->getMorphClass()}";
        }

        return "{$this->prefix}:{$this->viewable->getMorphClass()}:{$key}";
    }

    private function digest(ViewsQuery $query, ?Granularity $granularity, ?string $grouping, ?int $limit): string
    {
        return hash('xxh128', serialize([
            $this->source,
            $this->connection->getName(),
            $this->connection->getDatabaseName(),
            $this->viewable?->getMorphClass(),
            $this->viewable?->getKey(),
            $query->period?->cacheSignature(),
            $query->unique,
            $query->collection,
            $query->timezone?->getName(),
            $query->viewer?->getMorphClass(),
            $query->viewer?->getKey(),
            $granularity?->value,
            $grouping,
            $limit,
        ]));
    }
}
