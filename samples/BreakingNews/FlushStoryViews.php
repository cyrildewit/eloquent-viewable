<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Samples\BreakingNews;

use Carbon\CarbonImmutable;
use CyrildeWit\EloquentViewable\Recording\Buffering\Flusher;
use Illuminate\Contracts\Cache\Repository;

class FlushStoryViews
{
    public const string FLUSHED_AT = 'breaking-news.views.flushed-at';

    public function __construct(
        private readonly Flusher $flusher,
        private readonly Repository $cache,
    ) {}

    /**
     * Lands every buffered view and remembers when, so the newsroom can say
     * how fresh its numbers are. The package's `views:flush` command does
     * the first half on its own; this exists for the second.
     *
     * @return int the number of views landed
     */
    public function __invoke(): int
    {
        $landed = $this->flusher->flush();

        $this->cache->forever(self::FLUSHED_AT, CarbonImmutable::now()->toIso8601String());

        return $landed;
    }
}
