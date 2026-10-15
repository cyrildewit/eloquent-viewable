<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Querying\Contracts;

use CyrildeWit\EloquentViewable\Querying\Recommendations\RecommendationRequest;
use CyrildeWit\EloquentViewable\Support\ViewsQuery;

/**
 * Implement this on a view source that reads what recommendations for one
 * recipient are scored from. The source only counts; the reader weighs and
 * ranks. It is kept apart from `ViewSource`, so a source of your own keeps
 * working without it.
 *
 * @phpstan-type Seed array{type: string, id: int|string, viewed_at: string}
 * @phpstan-type Pair array{seed_type: string, seed_id: int|string, type: string, id: int|string, visitors: int}
 * @phpstan-type Audience array{type: string, id: int|string, visitors: int}
 * @phpstan-type RecommendationPairs array{seeds: list<Seed>, pairs: list<Pair>, audiences: list<Audience>}
 */
interface RanksRecommendations
{
    /**
     * The seeds are the viewables the recipient viewed most recently, newest
     * first, each with the moment it last viewed them. A pair is a seed and a
     * candidate of the `among` type, with the number of distinct visitors who
     * viewed both, left out below the minimum. The visitors of the recipient
     * never count towards a pair, a seed is never a candidate, and neither is
     * anything else the recipient ever viewed unless the request includes
     * what it has seen. The audiences hold the distinct visitors of every
     * viewable in a pair. The query is matched on every side.
     *
     * @return RecommendationPairs
     */
    public function recommendationPairs(RecommendationRequest $request, ViewsQuery $query): array;
}
