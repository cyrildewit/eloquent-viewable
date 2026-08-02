<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable;

use CyrildeWit\EloquentViewable\Contracts\Viewable;
use CyrildeWit\EloquentViewable\Support\Period;

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
 * share a key when they are genuinely the same count.
 */
final readonly class CacheKey
{
    public function __construct(
        private Viewable $viewable,
        private string $prefix,
    ) {}

    public function make(?Period $period = null, bool $unique = false, ?string $collection = null): string
    {
        return $this->head().':'.$this->digest($period, $unique, $collection);
    }

    private function head(): string
    {
        $key = $this->viewable->getKey();

        if ($key === null) {
            return "{$this->prefix}:type:{$this->viewable->getMorphClass()}";
        }

        return "{$this->prefix}:{$this->viewable->getMorphClass()}:{$key}";
    }

    private function digest(?Period $period, bool $unique, ?string $collection): string
    {
        $connection = $this->viewable->getConnection();

        return hash('xxh128', serialize([
            $connection->getName(),
            $connection->getDatabaseName(),
            $this->viewable->getMorphClass(),
            $this->viewable->getKey(),
            $period?->cacheSignature(),
            $unique,
            $collection,
        ]));
    }
}
