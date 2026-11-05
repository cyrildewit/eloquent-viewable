<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Benchmarks\Support;

/**
 * The presets a dataset can be seeded at. Views spread over the articles on
 * a power law, so one article is far hotter than the rest. `make
 * bench-describe` reports the hot and cold counts of a seeded dataset.
 */
enum DatasetSize: string
{
    case Small = 'small';

    case Medium = 'medium';

    case Large = 'large';

    public function views(): int
    {
        return match ($this) {
            self::Small => 1_000_000,
            self::Medium => 10_000_000,
            self::Large => 50_000_000,
        };
    }

    public function articles(): int
    {
        return match ($this) {
            self::Small => 1_000,
            self::Medium => 10_000,
            self::Large => 50_000,
        };
    }

    public function videos(): int
    {
        return intdiv($this->articles(), 10);
    }
}
