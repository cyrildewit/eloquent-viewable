<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Benchmarks\Maintenance;

use CyrildeWit\EloquentViewable\Benchmarks\Support\BenchCase;
use CyrildeWit\EloquentViewable\Benchmarks\Support\Dataset;
use CyrildeWit\EloquentViewable\Benchmarks\Support\Rollups;
use CyrildeWit\EloquentViewable\Querying\Rollups\Contracts\StateStore;
use CyrildeWit\EloquentViewable\Retention\Actions\AnonymiseViews;
use Generator;
use Illuminate\Container\Container;
use PhpBench\Attributes\AfterMethods;
use PhpBench\Attributes\BeforeMethods;
use PhpBench\Attributes\Groups;
use PhpBench\Attributes\Iterations;
use PhpBench\Attributes\ParamProviders;
use PhpBench\Attributes\Revs;

/**
 * `views:anonymise` over the last day before the anchor, in chunks of a
 * thousand and of five thousand views: each chunk selects the next views of
 * the day after the last id of the chunk before, and re-hashes them in one
 * `update … case`. Every iteration runs in a transaction that is rolled back,
 * so the seeded rows are never changed.
 */
#[Groups(['maintenance'])]
#[BeforeMethods(['setUp', 'begin'])]
#[AfterMethods('rollBack')]
#[Revs(1)]
#[Iterations(5)]
final class AnonymiseViewsBench extends BenchCase
{
    #[\Override]
    public function setUp(): void
    {
        parent::setUp();

        Rollups::install($this->connection());
    }

    /**
     * @return Generator<string, array{chunk: int}>
     */
    public function provideChunks(): Generator
    {
        yield '1,000 per chunk' => ['chunk' => 1_000];
        yield '5,000 per chunk' => ['chunk' => 5_000];
    }

    /**
     * Everything before the day is marked as anonymised already, so the run
     * covers that one day.
     */
    public function begin(): void
    {
        $this->connection()->beginTransaction();

        Container::getInstance()
            ->make(StateStore::class)
            ->put(StateStore::Anonymised, Dataset::anchor()->subDay()->format(StateStore::Format));
    }

    public function rollBack(): void
    {
        $this->connection()->rollBack();
    }

    /**
     * @param  array{chunk: int}  $params
     */
    #[ParamProviders('provideChunks')]
    public function benchAnonymiseDay(array $params): void
    {
        Container::getInstance()
            ->make(AnonymiseViews::class)
            ->handle(Dataset::anchor(), ['visitor', 'viewer', 'context'], $params['chunk']);
    }
}
