<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Benchmarks\Recording;

use CyrildeWit\EloquentViewable\Benchmarks\Models\Article;
use CyrildeWit\EloquentViewable\Benchmarks\Support\BenchCase;
use CyrildeWit\EloquentViewable\Benchmarks\Support\Dataset;
use Generator;
use PhpBench\Attributes\AfterMethods;
use PhpBench\Attributes\BeforeMethods;
use PhpBench\Attributes\Groups;
use PhpBench\Attributes\Iterations;
use PhpBench\Attributes\ParamProviders;
use PhpBench\Attributes\Revs;

/**
 * `views($article)->destroy()`: a delete narrowed by the composite index.
 * Each iteration first gives a throwaway article the number of views to
 * destroy, outside the timed region, so the seeded rows are never touched.
 */
#[Groups(['write'])]
#[BeforeMethods(['setUp', 'insertViewsToDestroy'])]
#[AfterMethods('removeArticle')]
#[Revs(1)]
#[Iterations(5)]
final class DestroyViewsBench extends BenchCase
{
    private Article $article;

    /**
     * @return Generator<string, array{views: int}>
     */
    public function provideCounts(): Generator
    {
        yield '100 views' => ['views' => 100];
        yield '1,000 views' => ['views' => 1_000];
        yield '10,000 views' => ['views' => 10_000];
    }

    /**
     * @param  array{views: int}  $params
     */
    public function insertViewsToDestroy(array $params): void
    {
        $this->article = Article::query()->create(['title' => 'Destroyed']);

        $anchor = Dataset::anchor();
        $rows = [];

        for ($i = 0; $i < $params['views']; $i++) {
            $rows[] = [
                'viewable_type' => Article::class,
                'viewable_id' => $this->article->getKey(),
                'visitor' => str_repeat('d', 80),
                'collection' => null,
                'viewed_at' => $anchor->subMinutes($i)->format('Y-m-d H:i:s'),
            ];

            if (count($rows) === 1_000) {
                $this->connection()->table('views')->insert($rows);
                $rows = [];
            }
        }

        if ($rows !== []) {
            $this->connection()->table('views')->insert($rows);
        }
    }

    public function removeArticle(): void
    {
        $this->connection()->table('views')->where('id', '>', $this->dataset->maxViewId)->delete();
        $this->article->delete();
    }

    /**
     * @param  array{views: int}  $params
     */
    #[ParamProviders('provideCounts')]
    public function benchDestroy(array $params): void
    {
        views($this->article)->destroy();
    }
}
