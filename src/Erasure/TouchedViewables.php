<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Erasure;

use CyrildeWit\EloquentViewable\Querying\Cache\CacheVersions;

/**
 * The viewables whose remembered counts an erasure changed. Past the limit
 * forgetting them one by one costs more than forgetting every count, so
 * that is what happens instead.
 *
 * @internal
 */
final class TouchedViewables
{
    public const int Limit = 100;

    /** @var array<string, array{string, int|string}> */
    private array $viewables = [];

    private bool $overflowed = false;

    public function add(string $type, int|string $key): void
    {
        if ($this->overflowed) {
            return;
        }

        $this->viewables["{$type}|{$key}"] = [$type, $key];

        if (count($this->viewables) > self::Limit) {
            $this->overflowed = true;
            $this->viewables = [];
        }
    }

    public function forget(CacheVersions $versions): void
    {
        if ($this->overflowed) {
            $versions->flushCache();

            return;
        }

        foreach ($this->viewables as [$type, $key]) {
            $versions->forgetModel($type, $key);
        }
    }
}
