<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Benchmarks\Querying;

use CyrildeWit\EloquentViewable\Benchmarks\Models\Article;
use CyrildeWit\EloquentViewable\Benchmarks\Support\BenchCase;
use CyrildeWit\EloquentViewable\Benchmarks\Support\Dataset;
use CyrildeWit\EloquentViewable\Benchmarks\Support\Rollups;
use CyrildeWit\EloquentViewable\Support\Period;
use PhpBench\Attributes\BeforeMethods;
use PhpBench\Attributes\Groups;
use PhpBench\Attributes\Iterations;
use PhpBench\Attributes\ParamProviders;
use PhpBench\Attributes\Revs;
use PhpBench\Attributes\Warmup;

/**
 * The reads of `CountViewsBench`, `OrderByViewsBench`, `TopViewedBench`,
 * `TrendingBench` and `GrowthBench` through the `rollup` source, with every
 * view folded into day and month rollups. Compare a subject with its
 * counterpart in those classes to see what the rollups save. Trending over the
 * past day is weighed per hour, which no day tier fits, so it reads the views
 * table. Unique counts still read the views table, which holds them exactly
 * while nothing is anonymised or pruned.
 */
#[Groups(['rollup'])]
#[BeforeMethods('setUp')]
#[Warmup(1)]
#[Revs(3)]
#[Iterations(5)]
final class RollupReadsBench extends BenchCase
{
    private const int PageSize = 20;

    #[\Override]
    public function setUp(): void
    {
        parent::setUp();

        Rollups::fold($this->connection(), $this->dataset);

        config()->set('eloquent-viewable.querying.source.driver', 'rollup');
    }

    /**
     * @param  array{target: string, days: int|null}  $params
     */
    #[ParamProviders(['provideTargets', 'providePeriods'])]
    public function benchCount(array $params): void
    {
        views($this->target($params))->period($this->period($params))->count();
    }

    /**
     * @param  array{days: int|null}  $params
     */
    #[ParamProviders('providePeriods')]
    public function benchOrderByViews(array $params): void
    {
        Article::query()->orderByViews('desc', $this->period($params))->limit(self::PageSize)->get();
    }

    /**
     * @param  array{days: int|null}  $params
     */
    #[ParamProviders('providePeriods')]
    public function benchTop(array $params): void
    {
        views(Article::class)->period($this->period($params))->top(10);
    }

    /**
     * The last day before the anchor against the same day on the four weeks
     * before, which the day tier answers.
     */
    public function benchAnomalies(): void
    {
        views(Article::class)->period($this->dataset->pastDays(1))->anomalies(limit: 10);
    }

    /**
     * @param  array{days: int|null}  $params
     */
    #[ParamProviders('providePeriods')]
    public function benchTrending(array $params): void
    {
        views(Article::class)->period($this->period($params) ?? Period::upto(Dataset::anchor()))->trending(10);
    }
}
