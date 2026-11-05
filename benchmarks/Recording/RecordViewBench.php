<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Benchmarks\Recording;

use CyrildeWit\EloquentViewable\Benchmarks\Models\Article;
use CyrildeWit\EloquentViewable\Benchmarks\Support\BenchCase;
use CyrildeWit\EloquentViewable\Benchmarks\Support\BenchmarkVisitor;
use PhpBench\Attributes\AfterMethods;
use PhpBench\Attributes\BeforeMethods;
use PhpBench\Attributes\Groups;
use PhpBench\Attributes\Iterations;
use PhpBench\Attributes\Revs;
use PhpBench\Attributes\Warmup;

/**
 * `views($article)->record()`: one insert into the full table, with the
 * primary key and both secondary indexes to maintain. The rows a run adds
 * are removed afterwards, so the dataset is unchanged for the next run.
 */
#[Groups(['write'])]
#[BeforeMethods(['setUp', 'prepareRecording'])]
#[AfterMethods('removeRecordedViews')]
#[Warmup(5)]
#[Revs(50)]
#[Iterations(5)]
final class RecordViewBench extends BenchCase
{
    private Article $article;

    private BenchmarkVisitor $visitor;

    public function prepareRecording(): void
    {
        $this->article = $this->dataset->hotArticle();
        $this->visitor = new BenchmarkVisitor(str_repeat('b', 80));
    }

    public function removeRecordedViews(): void
    {
        $this->connection()->table('views')->where('id', '>', $this->dataset->maxViewId)->delete();
    }

    public function benchRecord(): void
    {
        views($this->article)->useVisitor($this->visitor)->record();
    }

    /**
     * The same insert, through the `StoreView` job on the sync queue, so the
     * difference with `benchRecord` is the dispatch overhead.
     */
    public function benchRecordQueued(): void
    {
        views($this->article)->useVisitor($this->visitor)->queue()->record();
    }
}
