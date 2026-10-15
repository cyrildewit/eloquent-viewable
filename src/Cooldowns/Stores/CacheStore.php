<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Cooldowns\Stores;

use CyrildeWit\EloquentViewable\Cooldowns\Contracts\CooldownStore;
use DateTimeInterface;
use Illuminate\Contracts\Cache\Repository;

final readonly class CacheStore implements CooldownStore
{
    public function __construct(
        private Repository $cache,
        private string $prefix,
    ) {}

    public function has(string $key): bool
    {
        return $this->cache->has($this->prefixed($key));
    }

    public function put(string $key, DateTimeInterface $expiresAt): void
    {
        $this->cache->put($this->prefixed($key), true, $expiresAt);
    }

    private function prefixed(string $key): string
    {
        return "{$this->prefix}:{$key}";
    }
}
