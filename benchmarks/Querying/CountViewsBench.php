<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Benchmarks\Querying;

use CyrildeWit\EloquentViewable\Benchmarks\Support\BenchCase;
use PhpBench\Attributes\BeforeMethods;
use PhpBench\Attributes\Groups;
use PhpBench\Attributes\Iterations;
use PhpBench\Attributes\ParamProviders;
use PhpBench\Attributes\Revs;
use PhpBench\Attributes\Warmup;

/**
 * `views($viewable)->count()`: one `count(*)` or `count(distinct visitor)`
 * narrowed by the composite index. The hot article shows how the count
 * scales with the rows inside the period, the cold one what the index
 * lookup itself costs, and the whole type what a count without a
 * `viewable_id` costs.
 */
#[Groups(['read'])]
#[BeforeMethods('setUp')]
#[Warmup(1)]
#[Revs(3)]
#[Iterations(5)]
final class CountViewsBench extends BenchCase
{
    /**
     * @param  array{target: string, days: int|null}  $params
     */
    #[ParamProviders(['provideTargets', 'providePeriods'])]
    public function benchCount(array $params): void
    {
        views($this->target($params))->period($this->period($params))->count();
    }

    /**
     * @param  array{target: string, days: int|null}  $params
     */
    #[ParamProviders(['provideTargets', 'providePeriods'])]
    public function benchUniqueCount(array $params): void
    {
        views($this->target($params))->period($this->period($params))->unique()->count();
    }
}
