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
 * `views($viewable)->collection('newsletter')->count()`: the plain count with
 * a filter on `collection`, which the composite index does not cover, so the
 * database reads every row in the period to check it. Same targets and
 * periods as `CountViewsBench`, so the two show what the filter adds.
 */
#[Groups(['read'])]
#[BeforeMethods('setUp')]
#[Warmup(1)]
#[Revs(3)]
#[Iterations(5)]
final class CountViewsInCollectionBench extends BenchCase
{
    private const string Collection = 'newsletter';

    /**
     * @param  array{target: string, days: int|null}  $params
     */
    #[ParamProviders(['provideTargets', 'providePeriods'])]
    public function benchCountInCollection(array $params): void
    {
        views($this->target($params))->period($this->period($params))->collection(self::Collection)->count();
    }

    /**
     * @param  array{target: string, days: int|null}  $params
     */
    #[ParamProviders(['provideTargets', 'providePeriods'])]
    public function benchUniqueCountInCollection(array $params): void
    {
        views($this->target($params))->period($this->period($params))->collection(self::Collection)->unique()->count();
    }
}
