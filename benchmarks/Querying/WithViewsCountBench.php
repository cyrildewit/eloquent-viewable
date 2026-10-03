<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Benchmarks\Querying;

use CyrildeWit\EloquentViewable\Benchmarks\Models\Article;
use CyrildeWit\EloquentViewable\Benchmarks\Support\BenchCase;
use PhpBench\Attributes\BeforeMethods;
use PhpBench\Attributes\Groups;
use PhpBench\Attributes\Iterations;
use PhpBench\Attributes\ParamProviders;
use PhpBench\Attributes\Revs;
use PhpBench\Attributes\Warmup;

/**
 * `Article::withViewsCount()`: a correlated count selected per article on a
 * page of twenty. Unlike `orderByViews()`, nothing sorts on the count, so
 * the database only counts the views of the articles on the page.
 */
#[Groups(['read'])]
#[BeforeMethods('setUp')]
#[Warmup(1)]
#[Revs(1)]
#[Iterations(5)]
final class WithViewsCountBench extends BenchCase
{
    private const int PAGE_SIZE = 20;

    /**
     * @param  array{days: int|null}  $params
     */
    #[ParamProviders('providePeriods')]
    public function benchWithViewsCount(array $params): void
    {
        Article::query()->withViewsCount($this->period($params))->limit(self::PAGE_SIZE)->get();
    }

    /**
     * @param  array{days: int|null}  $params
     */
    #[ParamProviders('providePeriods')]
    public function benchWithUniqueViewsCount(array $params): void
    {
        Article::query()->withViewsCount($this->period($params), unique: true)->limit(self::PAGE_SIZE)->get();
    }
}
