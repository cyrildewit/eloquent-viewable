<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Querying\Recommendations;

use CyrildeWit\EloquentViewable\Contracts\Viewable;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Model;

/**
 * A score is only comparable with the other scores of the same call, because
 * it depends on the seeds and audiences that call read. The reasons are the models the
 * recipient viewed that added the most to it, most first.
 */
final readonly class Recommendation
{
    /** @param  EloquentCollection<int, Model&Viewable>  $because */
    public function __construct(
        public Model&Viewable $viewable,
        public float $score,
        public int $rank,
        public EloquentCollection $because,
    ) {}
}
