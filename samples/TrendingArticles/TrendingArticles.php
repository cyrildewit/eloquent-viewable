<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Samples\TrendingArticles;

use CyrildeWit\EloquentViewable\Support\Period;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Database\Eloquent\Collection;

class TrendingArticles
{
    private const string CACHE_KEY = 'samples.trending-articles';

    /**
     * How stale the ranking may be. `orderByViews()` counts every view in the
     * window on each call, which gets slow as the views table grows, and it
     * cannot use `remember()`. The ranking is cached here instead.
     */
    private const int CACHE_SECONDS = 600;

    private const int WINDOW_DAYS = 7;

    public function __construct(private readonly Repository $cache) {}

    /**
     * The most viewed articles of the past week, most viewed first, each
     * with its `views_count` for that week.
     *
     * @return Collection<int, Article>
     */
    public function get(int $limit = 10): Collection
    {
        $ranking = $this->ranking($limit);
        $position = array_flip(array_keys($ranking));

        // Only the ranking is cached, not the models, so an edited title
        // shows up straight away instead of after the cache expires.
        return Article::query()
            ->findMany(array_keys($ranking))
            ->each(function (Article $article) use ($ranking): void {
                $article->views_count = $ranking[$article->id];
            })
            ->sortBy(fn (Article $article): int => $position[$article->id])
            ->values();
    }

    /**
     * @return array<int, int> view counts keyed by article id, in rank order
     */
    private function ranking(int $limit): array
    {
        /** @var array<int, int> */
        return $this->cache->remember(self::CACHE_KEY.'.'.$limit, self::CACHE_SECONDS, fn (): array => Article::query()
            ->orderByViews('desc', Period::pastDays(self::WINDOW_DAYS))
            ->orderByDesc('id')
            ->limit($limit)
            ->get()
            ->filter(fn (Article $article): bool => $article->views_count > 0)
            ->mapWithKeys(fn (Article $article): array => [$article->id => (int) $article->views_count])
            ->all());
    }
}
