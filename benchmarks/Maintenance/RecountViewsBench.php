<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Benchmarks\Maintenance;

use CyrildeWit\EloquentViewable\Benchmarks\Models\Article;
use CyrildeWit\EloquentViewable\Benchmarks\Support\BenchCase;
use CyrildeWit\EloquentViewable\Benchmarks\Support\Dataset;
use CyrildeWit\EloquentViewable\Benchmarks\Support\Rollups;
use CyrildeWit\EloquentViewable\Maintenance\Actions\RecountChangedViews;
use Generator;
use Illuminate\Container\Container;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Database\Schema\Blueprint;
use PhpBench\Attributes\AfterMethods;
use PhpBench\Attributes\BeforeMethods;
use PhpBench\Attributes\Groups;
use PhpBench\Attributes\Iterations;
use PhpBench\Attributes\ParamProviders;
use PhpBench\Attributes\Revs;

/**
 * `views:recount` writing a `views_count` column on every article, against a
 * recount of only the articles that got a view since the last one. The column
 * is added to the seeded table the first time, and every iteration runs in a
 * transaction that is rolled back.
 */
#[Groups(['maintenance'])]
#[BeforeMethods(['setUp', 'begin'])]
#[AfterMethods('rollBack')]
#[Revs(1)]
#[Iterations(5)]
final class RecountViewsBench extends BenchCase
{
    #[\Override]
    public function setUp(): void
    {
        parent::setUp();

        $connection = $this->connection();

        Rollups::install($connection);

        if (! $connection->getSchemaBuilder()->hasColumn('articles', 'views_count')) {
            $connection->getSchemaBuilder()->table('articles', function (Blueprint $table): void {
                $table->unsignedInteger('views_count')->default(0);
            });
        }

        Container::getInstance()
            ->make(Repository::class)
            ->set('eloquent-viewable.querying.counters', [Article::class => ['views_count']]);
    }

    /** @return Generator<string, array{changed: int}> */
    public function provideChanges(): Generator
    {
        yield '10 articles viewed' => ['changed' => 10];
        yield '100 articles viewed' => ['changed' => 100];
    }

    /**
     * The first recount runs once, outside the transaction, so later
     * iterations start from a recount that has seen every view.
     *
     * @param  array{changed?: int}  $params
     */
    public function begin(array $params): void
    {
        $recount = Container::getInstance()->make(RecountChangedViews::class);

        $recount->handle(1_000);

        $this->connection()->beginTransaction();

        $rows = [];

        for ($article = 1; $article <= ($params['changed'] ?? 0); $article++) {
            $rows[] = [
                'viewable_type' => Article::class,
                'viewable_id' => $article,
                'visitor' => 'recounted',
                'collection' => null,
                'viewed_at' => Dataset::anchor()->format('Y-m-d H:i:s'),
            ];
        }

        if ($rows !== []) {
            $this->connection()->table('views')->insert($rows);
        }
    }

    public function rollBack(): void
    {
        $this->connection()->rollBack();
    }

    public function benchRecountEvery(): void
    {
        Container::getInstance()->make(RecountChangedViews::class)->handle(1_000, full: true);
    }

    /** @param  array{changed: int}  $params */
    #[ParamProviders('provideChanges')]
    public function benchRecountChanged(array $params): void
    {
        Container::getInstance()->make(RecountChangedViews::class)->handle(1_000);
    }
}
