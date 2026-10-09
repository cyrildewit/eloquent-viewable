<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Querying\Recommendations;

use CyrildeWit\EloquentViewable\Contracts\Viewable;

/**
 * A request names whom the recommendations are for and how far a source
 * reads: the `seeds` most recent viewables of the recipient, the `maxVisitors`
 * most recent visitors of each seed, and only pairs at least `minimum`
 * visitors share. With `includeSeen`, a candidate the recipient viewed before
 * stays in.
 */
final readonly class RecommendationRequest
{
    public function __construct(
        public Recipient $recipient,
        public ?Viewable $among,
        public int $seeds,
        public int $minimum,
        public ?int $maxVisitors,
        public bool $includeSeen = false,
    ) {}

    /** The identity keeps the remembered pairs of two requests apart. */
    public function identity(): string
    {
        $maxVisitors = $this->maxVisitors ?? 'all';
        $seen = $this->includeSeen ? 'seen' : 'unseen';

        return "{$this->recipient->identity()}:{$this->among?->getMorphClass()}:{$this->seeds}:{$this->minimum}:{$maxVisitors}:{$seen}";
    }
}
