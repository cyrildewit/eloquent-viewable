<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Samples\AuthorDigest;

use Carbon\CarbonImmutable;
use CyrildeWit\EloquentViewable\Querying\Comparison\ViewComparison;

/**
 * One author's week, counted when the digest is built. It holds titles and
 * numbers rather than models, so the queued notification sends what was
 * counted and does not load anything again.
 */
final readonly class WeeklyDigest
{
    public function __construct(
        /** Monday at midnight on the author's clock. */
        public CarbonImmutable $week,
        /** The views of every essay of the author, against the week before. */
        public ViewComparison $views,
        /** @var list<array{title: string, views: int}> the most viewed essays of the week, most viewed first */
        public array $top,
    ) {}
}
