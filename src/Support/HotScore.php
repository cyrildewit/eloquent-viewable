<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Support;

use Carbon\CarbonInterface;

/**
 * A hot score ranks a model by its views and by when it was made, the way
 * Reddit ranks posts: the logarithm of the count plus a term that grows with
 * the timestamp in the `from` column. A model `every` newer needs a tenth of
 * the views to score the same, so new content gets a head start. The time
 * term never changes for a model, so a stored score never has to decay.
 */
final readonly class HotScore
{
    public function __construct(
        public string $from,
        public Duration $every,
    ) {}

    /**
     * A model without a timestamp scores on its views alone, below every
     * model with one.
     */
    public function score(int $count, ?CarbonInterface $at): float
    {
        $views = log10(max($count, 1));

        if (! $at instanceof CarbonInterface) {
            return $views;
        }

        return $views + $at->getTimestamp() / $this->every->toInterval()->totalSeconds;
    }

    public function signature(): string
    {
        return "{$this->from}:{$this->every->shorthand()}";
    }
}
