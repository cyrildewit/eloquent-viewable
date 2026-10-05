<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Querying\Ranking;

use CyrildeWit\EloquentViewable\Contracts\Viewable;
use Illuminate\Database\Eloquent\Model;

/**
 * The score is the views weighed by their age, in a trending ranking only.
 */
final readonly class Entry
{
    public function __construct(
        public Model&Viewable $viewable,
        public int $count,
        public int $rank,
        public ?float $score = null,
    ) {}
}
