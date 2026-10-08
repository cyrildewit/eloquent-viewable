<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Querying\Ranking;

use CyrildeWit\EloquentViewable\Contracts\Viewable;
use CyrildeWit\EloquentViewable\Querying\Growth\Baseline;
use Illuminate\Database\Eloquent\Model;

/**
 * The score is the views weighed by their age in a trending ranking, and the
 * growth in a ranking by `rising()` or `anomalies()`, which also hold the
 * baseline the count was compared with.
 */
final readonly class Entry
{
    public function __construct(
        public Model&Viewable $viewable,
        public int $count,
        public int $rank,
        public ?float $score = null,
        public ?Baseline $baseline = null,
    ) {}
}
