<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Benchmarks\Dimensions;

use CyrildeWit\EloquentViewable\Benchmarks\Models\Article;
use CyrildeWit\EloquentViewable\Benchmarks\Support\BenchCase;
use CyrildeWit\EloquentViewable\Benchmarks\Support\BenchmarkVisitor;
use CyrildeWit\EloquentViewable\Benchmarks\Support\Dataset;
use CyrildeWit\EloquentViewable\Benchmarks\Support\Rollups;
use CyrildeWit\EloquentViewable\Dimensions\Campaign;
use CyrildeWit\EloquentViewable\Dimensions\Country;
use CyrildeWit\EloquentViewable\Dimensions\Device;
use CyrildeWit\EloquentViewable\Dimensions\Medium;
use CyrildeWit\EloquentViewable\Dimensions\Source;
use CyrildeWit\EloquentViewable\Querying\Rollups\Actions\FoldViews;
use CyrildeWit\EloquentViewable\Querying\Rollups\Contracts\StateStore;
use CyrildeWit\EloquentViewable\Querying\Rollups\Tier;
use DateTimeZone;
use Generator;
use Illuminate\Container\Container;
use PhpBench\Attributes\AfterMethods;
use PhpBench\Attributes\BeforeMethods;
use PhpBench\Attributes\Groups;
use PhpBench\Attributes\Iterations;
use PhpBench\Attributes\ParamProviders;
use PhpBench\Attributes\Revs;
use PhpBench\Attributes\Warmup;

/**
 * What dimensions cost: recording a view with the five common dimensions
 * against none, `countBy('source')` read from the views table, a whole type's
 * sources over a year read from the `views:source` rollup, and folding a day
 * of that rollup with the cap and without. The seeder fills the source and
 * the device of every view. The rows a recording run adds are removed
 * afterwards, so the dataset is unchanged for the next run.
 */
#[Groups(['dimensions'])]
#[BeforeMethods(['setUp', 'configureDimensions'])]
#[AfterMethods('removeRecordedViews')]
#[Warmup(1)]
#[Revs(5)]
#[Iterations(5)]
final class DimensionsBench extends BenchCase
{
    /**
     * Noted in the state table once the source rollup is folded, so the next
     * process reads the same rows without folding them again.
     */
    private const string FoldedFrom = 'bench:dimensions_folded_from';

    private const array Dimensions = [
        'source' => Source::class,
        'medium' => Medium::class,
        'campaign' => Campaign::class,
        'device' => Device::class,
        'country' => Country::class,
    ];

    private BenchmarkVisitor $visitor;

    /**
     * Configures the dimensions, and folds the source rollup for a subject
     * that reads or folds it, before the timed region. Called without
     * parameters by `make bench-explain`, which gets every dimension.
     *
     * @param  array{dimensions?: int, cap?: bool, rollup?: bool}  $params
     */
    public function configureDimensions(array $params = []): void
    {
        $this->visitor = new BenchmarkVisitor(str_repeat('d', 80));

        $definitions = array_slice(self::Dimensions, 0, $params['dimensions'] ?? count(self::Dimensions), preserve_keys: true);

        if (isset($definitions['source'])) {
            $definitions['source'] = [Source::class, 'maxValues' => ($params['cap'] ?? true) ? 20 : null];
        }

        config()->set('eloquent-viewable.dimensions.definitions', $definitions);

        if ($params['rollup'] ?? false) {
            $this->foldSources();
        }
    }

    public function removeRecordedViews(): void
    {
        $this->connection()->table('views')->where('id', '>', $this->dataset->maxViewId)->delete();
    }

    /**
     * @return Generator<string, array{dimensions: int}>
     */
    public function provideDimensions(): Generator
    {
        yield 'no dimensions' => ['dimensions' => 0];
        yield 'five dimensions' => ['dimensions' => 5];
    }

    /**
     * @return Generator<string, array{rollup: bool}>
     */
    public function provideRollupRead(): Generator
    {
        yield 'all articles,past year' => ['rollup' => true];
    }

    /**
     * @return Generator<string, array{cap: bool, rollup: bool}>
     */
    public function provideCaps(): Generator
    {
        yield 'with the cap' => ['cap' => true, 'rollup' => true];
        yield 'without the cap' => ['cap' => false, 'rollup' => true];
    }

    /**
     * @param  array{dimensions: int}  $params
     */
    #[ParamProviders('provideDimensions')]
    #[Revs(50)]
    public function benchRecord(array $params): void
    {
        views($this->dataset->hotArticle())->useVisitor($this->visitor)->record();
    }

    /**
     * @param  array{target: string, days: int|null}  $params
     */
    #[ParamProviders(['provideTargets', 'providePeriods'])]
    public function benchCountBy(array $params): void
    {
        views($this->target($params))->period($this->period($params))->countBy('source', limit: 10);
    }

    /**
     * @param  array{rollup: bool}  $params
     */
    #[ParamProviders('provideRollupRead')]
    public function benchCountByThroughRollups(array $params): void
    {
        views(Article::class)->period($this->dataset->pastDays(365))->countBy('source', limit: 10);
    }

    /**
     * Folds the last day before the anchor again, a delete and one insert per
     * grouping for the top values, and one more for `other` with the cap.
     *
     * @param  array{cap: bool, rollup: bool}  $params
     */
    #[ParamProviders('provideCaps')]
    #[Revs(1)]
    public function benchFoldDay(array $params): void
    {
        $last = Tier::Day->floor(Dataset::anchor()->subSecond(), new DateTimeZone('UTC'));

        Container::getInstance()->make(FoldViews::class)->handle(Tier::Day, $last, rollup: 'views:source');
    }

    /**
     * The built-in rollups first, folded once per dataset, then the source
     * rollup on top, once per dataset too.
     */
    private function foldSources(): void
    {
        Rollups::fold($this->connection(), $this->dataset);

        config()->set('eloquent-viewable.retention.rollups.dimensions', ['source']);
        config()->set('eloquent-viewable.querying.source.driver', 'rollup');

        $state = Container::getInstance()->make(StateStore::class);
        $marker = "{$this->dataset->size->value}:{$this->dataset->seed}:{$this->dataset->seededAt}";

        if ($state->get(self::FoldedFrom) === $marker) {
            return;
        }

        $this->connection()->table('view_rollups')->where('rollup', 'views:source')->delete();

        Container::getInstance()->make(FoldViews::class)->handle(rollup: 'views:source');

        $state->put(self::FoldedFrom, $marker);
    }
}
