<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Benchmarks\Querying;

use CyrildeWit\EloquentViewable\Benchmarks\Models\Article;
use CyrildeWit\EloquentViewable\Benchmarks\Support\BenchCase;
use CyrildeWit\EloquentViewable\Benchmarks\Support\Rollups;
use Generator;
use PhpBench\Attributes\BeforeMethods;
use PhpBench\Attributes\Groups;
use PhpBench\Attributes\Iterations;
use PhpBench\Attributes\ParamProviders;
use PhpBench\Attributes\Revs;
use PhpBench\Attributes\Warmup;

/**
 * The reads of `RollupReadsBench` with the last day before the anchor left
 * unfolded, the way a production table always has views newer than its last
 * fold. The rollup source reads that day from the views table and the rest
 * from the rollups, so the scopes run their views-table part on every row.
 * Over thirty days, trending is weighed per day, which the day tier answers.
 */
#[Groups(['rollup'])]
#[BeforeMethods('setUp')]
#[Warmup(1)]
#[Revs(3)]
#[Iterations(5)]
final class RollupHandOverBench extends BenchCase
{
    private const int PageSize = 20;

    private const int Threshold = 100;

    #[\Override]
    public function setUp(): void
    {
        parent::setUp();

        Rollups::fold($this->connection(), $this->dataset);
        Rollups::leaveLastDayUnfolded();

        config()->set('eloquent-viewable.querying.source.driver', 'rollup');
    }

    /**
     * @return Generator<string, array{days: int}>
     */
    public function provideHandOverPeriods(): Generator
    {
        yield 'past 30 days' => ['days' => 30];
        yield 'past 7 days' => ['days' => 7];
    }

    /**
     * @param  array{target: string, days: int}  $params
     */
    #[ParamProviders(['provideTargets', 'provideHandOverPeriods'])]
    public function benchCount(array $params): void
    {
        views($this->target($params))->period($this->period($params))->count();
    }

    /**
     * @param  array{days: int}  $params
     */
    #[ParamProviders('provideHandOverPeriods')]
    public function benchOrderByViews(array $params): void
    {
        Article::query()->orderByViews('desc', $this->period($params))->limit(self::PageSize)->get();
    }

    /**
     * @param  array{days: int}  $params
     */
    #[ParamProviders('provideHandOverPeriods')]
    public function benchWhereViewsCount(array $params): void
    {
        Article::query()->whereViewsCount('>=', self::Threshold, $this->period($params))->limit(self::PageSize)->get();
    }

    public function benchOrderByTrending(): void
    {
        Article::query()->orderByTrending('desc', $this->dataset->pastDays(30))->limit(self::PageSize)->get();
    }
}
