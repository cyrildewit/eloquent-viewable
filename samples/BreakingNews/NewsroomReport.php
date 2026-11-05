<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Samples\BreakingNews;

use Carbon\CarbonImmutable;

final readonly class NewsroomReport
{
    /** @param  array<string, int>  $views  today's views, keyed by headline */
    public function __construct(
        public array $views,
        public ?CarbonImmutable $asOf,
    ) {}
}
