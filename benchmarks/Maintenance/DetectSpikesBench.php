<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Benchmarks\Maintenance;

use Carbon\CarbonImmutable;
use CyrildeWit\EloquentViewable\Benchmarks\Models\Article;
use CyrildeWit\EloquentViewable\Benchmarks\Support\BenchCase;
use CyrildeWit\EloquentViewable\Benchmarks\Support\Dataset;
use CyrildeWit\EloquentViewable\Spikes\Actions\DetectSpikes;
use Generator;
use Illuminate\Container\Container;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Support\Carbon;
use PhpBench\Attributes\AfterMethods;
use PhpBench\Attributes\BeforeMethods;
use PhpBench\Attributes\Groups;
use PhpBench\Attributes\Iterations;
use PhpBench\Attributes\ParamProviders;
use PhpBench\Attributes\Revs;

/**
 * `views:detect-spikes` watching every article, over the last hour before the
 * anchor against the same hour on the four weeks before, from an empty
 * episodes table. Every iteration runs in a transaction that is rolled back,
 * so each one opens the same episodes.
 */
#[Groups(['maintenance'])]
#[BeforeMethods(['setUp', 'begin'])]
#[AfterMethods('rollBack')]
#[Revs(1)]
#[Iterations(5)]
final class DetectSpikesBench extends BenchCase
{
    #[\Override]
    public function setUp(): void
    {
        parent::setUp();

        $connection = $this->connection();

        if (! $connection->getSchemaBuilder()->hasTable('view_spikes')) {
            require_once __DIR__.'/../../database/migrations/create_view_spikes_table.php.stub';

            new \CreateViewSpikesTable()->up();
        }

        CarbonImmutable::setTestNow(Dataset::anchor()->addMinutes(30));
        Carbon::setTestNow(Dataset::anchor()->addMinutes(30));
    }

    /** @return Generator<string, array{drops: bool}> */
    public function provideDirections(): Generator
    {
        yield 'spikes' => ['drops' => false];
        yield 'spikes and drops' => ['drops' => true];
    }

    /** @param  array{drops?: bool}  $params */
    public function begin(array $params): void
    {
        Container::getInstance()
            ->make(Repository::class)
            ->set('eloquent-viewable.spikes.types', [Article::class => ['drops' => $params['drops'] ?? false]]);

        $this->connection()->beginTransaction();
    }

    public function rollBack(): void
    {
        $this->connection()->rollBack();
    }

    /** @param  array{drops: bool}  $params */
    #[ParamProviders('provideDirections')]
    public function benchDetect(array $params): void
    {
        Container::getInstance()->make(DetectSpikes::class)->handle();
    }
}
