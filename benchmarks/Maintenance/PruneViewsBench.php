<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Benchmarks\Maintenance;

use Carbon\CarbonImmutable;
use CyrildeWit\EloquentViewable\Benchmarks\Support\BenchCase;
use CyrildeWit\EloquentViewable\Benchmarks\Support\Rollups;
use CyrildeWit\EloquentViewable\Retention\Actions\PruneViews;
use Generator;
use Illuminate\Container\Container;
use PhpBench\Attributes\AfterMethods;
use PhpBench\Attributes\BeforeMethods;
use PhpBench\Attributes\Groups;
use PhpBench\Attributes\Iterations;
use PhpBench\Attributes\ParamProviders;
use PhpBench\Attributes\Revs;

/**
 * `views:prune` deleting the oldest day of views, in chunks of a thousand and
 * of five thousand: each chunk selects the ids of views before the cutoff and
 * deletes them by id. Every iteration runs in a transaction that is rolled
 * back, so the seeded rows are never deleted.
 */
#[Groups(['maintenance'])]
#[BeforeMethods(['setUp', 'begin'])]
#[AfterMethods('rollBack')]
#[Revs(1)]
#[Iterations(5)]
final class PruneViewsBench extends BenchCase
{
    private CarbonImmutable $cutoff;

    #[\Override]
    public function setUp(): void
    {
        parent::setUp();

        Rollups::install($this->connection());

        /** @var string $oldest */
        $oldest = $this->connection()->table('views')->min('viewed_at');

        $this->cutoff = CarbonImmutable::parse($oldest)->startOfDay()->addDay();
    }

    /**
     * @return Generator<string, array{chunk: int}>
     */
    public function provideChunks(): Generator
    {
        yield '1,000 per chunk' => ['chunk' => 1_000];
        yield '5,000 per chunk' => ['chunk' => 5_000];
    }

    public function begin(): void
    {
        $this->connection()->beginTransaction();
    }

    public function rollBack(): void
    {
        $this->connection()->rollBack();
    }

    /**
     * @param  array{chunk: int}  $params
     */
    #[ParamProviders('provideChunks')]
    public function benchPruneDay(array $params): void
    {
        Container::getInstance()->make(PruneViews::class)->handle($this->cutoff, $params['chunk']);
    }
}
