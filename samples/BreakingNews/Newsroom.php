<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Samples\BreakingNews;

use Carbon\CarbonImmutable;
use CyrildeWit\EloquentViewable\Support\Period;
use Illuminate\Contracts\Cache\Repository;

class Newsroom
{
    public function __construct(private readonly Repository $cache) {}

    /**
     * Today's views per story, from the views table. Views still in the
     * buffer are not in it, which is what `asOf` tells the reader.
     */
    public function today(): NewsroomReport
    {
        $views = [];

        foreach (Story::query()->orderBy('id')->get() as $story) {
            $views[$story->headline] = views($story)->period(Period::since(CarbonImmutable::today()))->count();
        }

        $flushedAt = $this->cache->get(FlushStoryViews::FlushedAt);

        return new NewsroomReport(
            views: $views,
            asOf: is_string($flushedAt) ? CarbonImmutable::parse($flushedAt) : null,
        );
    }
}
