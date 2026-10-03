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

#[Groups(['read'])]
#[BeforeMethods('setUp')]
#[Warmup(1)]
#[Revs(1)]
#[Iterations(5)]
final class WhereViewsCountBench extends BenchCase
{
    private const int PAGE_SIZE = 20;

    private const int THRESHOLD = 100;

    /**
     * @param  array{days: int|null}  $params
     */
    #[ParamProviders('providePeriods')]
    public function benchWhereViewsCount(array $params): void
    {
        Article::query()->whereViewsCount('>=', self::THRESHOLD, $this->period($params))->limit(self::PAGE_SIZE)->get();
    }

    /**
     * @param  array{days: int|null}  $params
     */
    #[ParamProviders('providePeriods')]
    public function benchWhereUniqueViewsCount(array $params): void
    {
        Article::query()->whereUniqueViewsCount('>=', self::THRESHOLD, $this->period($params))->limit(self::PAGE_SIZE)->get();
    }
}
