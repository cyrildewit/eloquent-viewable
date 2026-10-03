<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Benchmarks\Querying;

use CyrildeWit\EloquentViewable\Benchmarks\Models\Article;
use CyrildeWit\EloquentViewable\Benchmarks\Support\BenchCase;
use CyrildeWit\EloquentViewable\Benchmarks\Support\Seeder;
use Generator;
use PhpBench\Attributes\BeforeMethods;
use PhpBench\Attributes\Groups;
use PhpBench\Attributes\Iterations;
use PhpBench\Attributes\ParamProviders;
use PhpBench\Attributes\Revs;
use PhpBench\Attributes\Warmup;
use RuntimeException;

/**
 * `Article::whereViewedByVisitor()` and its `Not` form: an `exists` per
 * article on the views of that article by one visitor. The composite index
 * finds the article's views, but no index covers `visitor`, so every one of
 * them is read until a match turns up. The returning visitor has the most
 * views in the dataset; the new visitor has none, so every probe reads all
 * of an article's views and finds nothing.
 */
#[Groups(['read'])]
#[BeforeMethods('setUp')]
#[Warmup(1)]
#[Revs(1)]
#[Iterations(5)]
final class WhereViewedBench extends BenchCase
{
    private const int PAGE_SIZE = 20;

    /**
     * @return Generator<string, array{visitor: string}>
     */
    public function provideVisitors(): Generator
    {
        yield 'returning visitor' => ['visitor' => 'returning'];
        yield 'new visitor' => ['visitor' => 'new'];
    }

    /**
     * @param  array{visitor: string, days: int|null}  $params
     */
    #[ParamProviders(['provideVisitors', 'providePeriods'])]
    public function benchWhereViewedByVisitor(array $params): void
    {
        Article::query()->whereViewedByVisitor($this->visitor($params), $this->period($params))->limit(self::PAGE_SIZE)->get();
    }

    /**
     * @param  array{visitor: string, days: int|null}  $params
     */
    #[ParamProviders(['provideVisitors', 'providePeriods'])]
    public function benchWhereNotViewedByVisitor(array $params): void
    {
        Article::query()->whereNotViewedByVisitor($this->visitor($params), $this->period($params))->limit(self::PAGE_SIZE)->get();
    }

    /**
     * @param  array{visitor: string}  $params
     */
    private function visitor(array $params): string
    {
        return match ($params['visitor']) {
            'returning' => Seeder::visitor(0),
            'new' => str_repeat('n', 80),
            default => throw new RuntimeException("Unknown visitor [{$params['visitor']}]."),
        };
    }
}
