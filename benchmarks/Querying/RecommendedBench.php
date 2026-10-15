<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Benchmarks\Querying;

use CyrildeWit\EloquentViewable\Benchmarks\Models\Article;
use CyrildeWit\EloquentViewable\Benchmarks\Support\BenchCase;
use CyrildeWit\EloquentViewable\Benchmarks\Support\BenchmarkVisitor;
use CyrildeWit\EloquentViewable\Benchmarks\Support\Seeder;
use Generator;
use Illuminate\Support\Facades\Config;
use PhpBench\Attributes\BeforeMethods;
use PhpBench\Attributes\Groups;
use PhpBench\Attributes\Iterations;
use PhpBench\Attributes\ParamProviders;
use PhpBench\Attributes\Revs;
use PhpBench\Attributes\Warmup;
use RuntimeException;

/**
 * `recommended()` and `recommendedFor()` read the most recent articles of one
 * visitor, then do what `alsoViewed()` does for each of them, so their cost
 * follows the number of seeds times the visitors read per seed. The
 * returning visitor has the most views in the dataset and fills every seed;
 * the occasional visitor has a handful.
 */
#[Groups(['read'])]
#[BeforeMethods('setUp')]
#[Warmup(1)]
#[Revs(1)]
#[Iterations(5)]
final class RecommendedBench extends BenchCase
{
    private const int Limit = 10;

    /**
     * @return Generator<string, array{visitor: string}>
     */
    public function provideVisitors(): Generator
    {
        yield 'returning visitor' => ['visitor' => 'returning'];
        yield 'occasional visitor' => ['visitor' => 'occasional'];
    }

    /**
     * @return Generator<string, array{max_visitors: int|null}>
     */
    public function provideCaps(): Generator
    {
        yield 'five hundred visitors' => ['max_visitors' => 500];
        yield 'every visitor' => ['max_visitors' => null];
    }

    /**
     * @param  array{visitor: string, max_visitors: int|null, days: int|null}  $params
     */
    #[ParamProviders(['provideVisitors', 'provideCaps', 'providePeriods'])]
    public function benchRecommended(array $params): void
    {
        Config::set('eloquent-viewable.querying.recommendations.max_visitors', $params['max_visitors']);

        views(Article::class)->useVisitor(new BenchmarkVisitor($this->visitor($params)))->period($this->period($params))->recommended(self::Limit);
    }

    /**
     * @param  array{visitor: string, max_visitors: int|null, days: int|null}  $params
     */
    #[ParamProviders(['provideVisitors', 'provideCaps', 'providePeriods'])]
    public function benchRecommendedFor(array $params): void
    {
        Config::set('eloquent-viewable.querying.recommendations.max_visitors', $params['max_visitors']);

        Article::query()->recommendedFor($this->visitor($params), $this->period($params))->limit(self::Limit)->get();
    }

    /**
     * @param  array{visitor: string}  $params
     */
    private function visitor(array $params): string
    {
        return match ($params['visitor']) {
            'returning' => Seeder::visitor(0),
            'occasional' => Seeder::visitor(500),
            default => throw new RuntimeException("Unknown visitor [{$params['visitor']}]."),
        };
    }
}
