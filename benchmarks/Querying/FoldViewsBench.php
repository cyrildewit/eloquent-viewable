<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Benchmarks\Querying;

use CyrildeWit\EloquentViewable\Benchmarks\Support\BenchCase;
use CyrildeWit\EloquentViewable\Benchmarks\Support\Dataset;
use CyrildeWit\EloquentViewable\Benchmarks\Support\Rollups;
use CyrildeWit\EloquentViewable\Querying\Rollups\Actions\FoldViews;
use CyrildeWit\EloquentViewable\Querying\Rollups\Tier;
use DateTimeZone;
use Generator;
use Illuminate\Container\Container;
use PhpBench\Attributes\BeforeMethods;
use PhpBench\Attributes\Groups;
use PhpBench\Attributes\Iterations;
use PhpBench\Attributes\ParamProviders;
use PhpBench\Attributes\Revs;
use PhpBench\Attributes\Warmup;

/**
 * `views:rollup` folding one bucket again, the last day or the last month
 * before the anchor: a delete and one `insert … select … group by` per
 * grouping. A bucket is folded whole, so the result is the same every rev.
 */
#[Groups(['rollup'])]
#[BeforeMethods('setUp')]
#[Warmup(1)]
#[Revs(1)]
#[Iterations(5)]
final class FoldViewsBench extends BenchCase
{
    #[\Override]
    public function setUp(): void
    {
        parent::setUp();

        Rollups::fold($this->connection(), $this->dataset);
    }

    /**
     * @return Generator<string, array{tier: string}>
     */
    public function provideTiers(): Generator
    {
        yield 'a day' => ['tier' => 'day'];
        yield 'a month' => ['tier' => 'month'];
    }

    /**
     * @param  array{tier: string}  $params
     */
    #[ParamProviders('provideTiers')]
    public function benchFoldBucket(array $params): void
    {
        $tier = Tier::from($params['tier']);
        $last = $tier->floor(Dataset::anchor()->subSecond(), new DateTimeZone('UTC'));

        Container::getInstance()->make(FoldViews::class)->handle($tier, $last);
    }
}
