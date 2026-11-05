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
 * `Article::orderByViews()`: a correlated count per article, sorted on the
 * result. The database has to count the views of every article before it
 * can return the first page, so the cost grows with articles times views
 * per article and the limit does not help.
 */
#[Groups(['read'])]
#[BeforeMethods('setUp')]
#[Warmup(1)]
#[Revs(1)]
#[Iterations(5)]
final class OrderByViewsBench extends BenchCase
{
    private const int PageSize = 20;

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
    public function benchOrderByUniqueViews(array $params): void
    {
        Article::query()->orderByUniqueViews('desc', $this->period($params))->limit(self::PageSize)->get();
    }
}
