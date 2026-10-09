<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Erasure;

use CyrildeWit\EloquentViewable\Erasure\Events\CountsChanged;

/**
 * It collects the viewables whose counts an erasure changed. Past the limit
 * forgetting and recounting them one by one costs more than doing so for
 * every count, so that is what happens instead.
 *
 * @internal
 */
final class TouchedViewables
{
    public const int Limit = 100;

    /** @var array<string, array<string, int|string>> */
    private array $viewables = [];

    private int $count = 0;

    private bool $overflowed = false;

    public function add(string $type, int|string $key): void
    {
        if ($this->overflowed) {
            return;
        }

        if (isset($this->viewables[$type][(string) $key])) {
            return;
        }

        $this->viewables[$type][(string) $key] = $key;
        $this->count++;

        if ($this->count > self::Limit) {
            $this->overflowed = true;
            $this->viewables = [];
        }
    }

    public function countsChanged(): CountsChanged
    {
        if ($this->overflowed) {
            return new CountsChanged(null);
        }

        return new CountsChanged(array_map(array_values(...), $this->viewables));
    }
}
