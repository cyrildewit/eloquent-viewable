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
 * `views($viewable)->countByCollection()`: a `group by` on the `collection`
 * column. The composite index narrows the rows like `count()` does, but no
 * index carries the column, so the database reads every row in the period to
 * group it. The same targets and periods as `CountViewsBench`, so the two
 * show what the grouping adds to a plain count.
 */
#[Groups(['read'])]
#[BeforeMethods('setUp')]
#[Warmup(1)]
#[Revs(3)]
#[Iterations(5)]
final class CountViewsByCollectionBench extends BenchCase
{
    /**
     * @param  array{target: string, days: int|null}  $params
     */
    #[ParamProviders(['provideTargets', 'providePeriods'])]
    public function benchCountByCollection(array $params): void
    {
        views($this->target($params))->period($this->period($params))->countByCollection();
    }

    /**
     * @param  array{target: string, days: int|null}  $params
     */
    #[ParamProviders(['provideTargets', 'providePeriods'])]
    public function benchUniqueCountByCollection(array $params): void
    {
        views($this->target($params))->period($this->period($params))->unique()->countByCollection();
    }
}
