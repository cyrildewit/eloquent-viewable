<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Querying\Recommendations;

/**
 * How closely a candidate follows a seed. `Cosine` divides the visitors the
 * two share by the root of the product of their own visitors, so something
 * everybody views does not follow every seed. `Count` takes the shared
 * visitors as they are, like `alsoViewed()`.
 */
enum Similarity: string
{
    case Cosine = 'cosine';
    case Count = 'count';

    public function between(int $shared, int $seedVisitors, int $candidateVisitors): float
    {
        if ($this === self::Count) {
            return (float) $shared;
        }

        return $shared / sqrt(max(1, $seedVisitors) * max(1, $candidateVisitors));
    }
}
