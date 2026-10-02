<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Benchmarks\Support;

/**
 * The presets a dataset can be seeded at. Views spread over the articles on
 * a power law, so the hottest article in a medium dataset carries about a
 * million views and the coldest a few dozen.
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
