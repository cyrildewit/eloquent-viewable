<?php

declare(strict_types=1);

use CyrildeWit\EloquentViewable\Benchmarks\Maintenance\AnonymiseViewsBench;
use CyrildeWit\EloquentViewable\Benchmarks\Maintenance\DetectSpikesBench;
use CyrildeWit\EloquentViewable\Benchmarks\Maintenance\PruneViewsBench;
use CyrildeWit\EloquentViewable\Benchmarks\Maintenance\RecountViewsBench;
use CyrildeWit\EloquentViewable\Benchmarks\Php\CooldownManagerBench;
use CyrildeWit\EloquentViewable\Benchmarks\Php\ViewSeriesBench;
use CyrildeWit\EloquentViewable\Benchmarks\Querying\AlsoViewedBench;
use CyrildeWit\EloquentViewable\Benchmarks\Querying\CountViewsBench;
use CyrildeWit\EloquentViewable\Benchmarks\Querying\CountViewsByCollectionBench;
use CyrildeWit\EloquentViewable\Benchmarks\Querying\CountViewsByIntervalBench;
use CyrildeWit\EloquentViewable\Benchmarks\Querying\CountViewsForViewablesBench;
use CyrildeWit\EloquentViewable\Benchmarks\Querying\CountViewsInCollectionBench;
use CyrildeWit\EloquentViewable\Benchmarks\Querying\FoldViewsBench;
use CyrildeWit\EloquentViewable\Benchmarks\Querying\GrowthBench;
use CyrildeWit\EloquentViewable\Benchmarks\Querying\OrderByViewsBench;
use CyrildeWit\EloquentViewable\Benchmarks\Querying\RecommendedBench;
use CyrildeWit\EloquentViewable\Benchmarks\Querying\RememberedCountsBench;
use CyrildeWit\EloquentViewable\Benchmarks\Querying\RollupReadsBench;
use CyrildeWit\EloquentViewable\Benchmarks\Querying\TopViewedBench;
use CyrildeWit\EloquentViewable\Benchmarks\Querying\TrendingBench;
use CyrildeWit\EloquentViewable\Benchmarks\Querying\WhereViewedBench;
use CyrildeWit\EloquentViewable\Benchmarks\Querying\WhereViewsCountBench;
use CyrildeWit\EloquentViewable\Benchmarks\Querying\WithViewsCountBench;
use CyrildeWit\EloquentViewable\Benchmarks\Recording\BufferViewsBench;
use CyrildeWit\EloquentViewable\Benchmarks\Recording\DestroyViewsBench;
use CyrildeWit\EloquentViewable\Benchmarks\Recording\RecordViewBench;
use CyrildeWit\EloquentViewable\Benchmarks\Support\Benchmark;
use CyrildeWit\EloquentViewable\Benchmarks\Support\Variant;
use CyrildeWit\EloquentViewable\Benchmarks\Support\Variants;

/**
 * The parameter set names here are the naming contract with the results
 * repository in test form: they must equal the `<parameter-set name="…">`
 * values in a phpbench dump of the same classes.
 */

/**
 * @param  class-string  $class
 */
function benchmark(string $class): Benchmark
{
    foreach (Variants::discover()->all() as $benchmark) {
        if ($benchmark->class === $class) {
            return $benchmark;
        }
    }

    throw new RuntimeException("No benchmark {$class} was discovered.");
}

/**
 * @param  class-string  $class
 * @return list<string>
 */
function setsOf(string $class, string $subject): array
{
    $variants = array_filter(benchmark($class)->variants, fn (Variant $variant): bool => $variant->subject === $subject);

    return array_values(array_map(fn (Variant $variant): string => $variant->set, $variants));
}

it('finds every benchmark class in path order', function (): void {
    $classes = array_map(fn (Benchmark $benchmark): string => $benchmark->class, Variants::discover()->all());

    expect($classes)->toBe([
        AnonymiseViewsBench::class,
        DetectSpikesBench::class,
        PruneViewsBench::class,
        RecountViewsBench::class,
        CooldownManagerBench::class,
        ViewSeriesBench::class,
        AlsoViewedBench::class,
        CountViewsBench::class,
        CountViewsByCollectionBench::class,
        CountViewsByIntervalBench::class,
        CountViewsForViewablesBench::class,
        CountViewsInCollectionBench::class,
        FoldViewsBench::class,
        GrowthBench::class,
        OrderByViewsBench::class,
        RecommendedBench::class,
        RememberedCountsBench::class,
        RollupReadsBench::class,
        TopViewedBench::class,
        TrendingBench::class,
        WhereViewedBench::class,
        WhereViewsCountBench::class,
        WithViewsCountBench::class,
        BufferViewsBench::class,
        DestroyViewsBench::class,
        RecordViewBench::class,
    ]);
});

it('filters the benchmarks on their group', function (): void {
    $names = fn (string $group): array => array_map(
        fn (Benchmark $benchmark): string => $benchmark->name(),
        Variants::discover()->inGroup($group),
    );

    expect($names('read'))->toBe(['AlsoViewedBench', 'CountViewsBench', 'CountViewsByCollectionBench', 'CountViewsByIntervalBench', 'CountViewsForViewablesBench', 'CountViewsInCollectionBench', 'GrowthBench', 'OrderByViewsBench', 'RecommendedBench', 'TopViewedBench', 'TrendingBench', 'WhereViewedBench', 'WhereViewsCountBench', 'WithViewsCountBench'])
        ->and($names('write'))->toBe(['BufferViewsBench', 'DestroyViewsBench', 'RecordViewBench'])
        ->and($names('php'))->toBe(['CooldownManagerBench', 'ViewSeriesBench'])
        ->and($names('cache'))->toBe(['RememberedCountsBench'])
        ->and($names('rollup'))->toBe(['FoldViewsBench', 'RollupReadsBench'])
        ->and($names('maintenance'))->toBe(['AnonymiseViewsBench', 'DetectSpikesBench', 'PruneViewsBench', 'RecountViewsBench'])
        ->and($names('missing'))->toBeEmpty();
});

it('lists the before-methods of a class in order', function (): void {
    expect(benchmark(CountViewsBench::class)->beforeMethods)->toBe(['setUp'])
        ->and(benchmark(DestroyViewsBench::class)->beforeMethods)->toBe(['setUp', 'insertViewsToDestroy'])
        ->and(benchmark(CountViewsForViewablesBench::class)->beforeMethods)->toBe(['setUp', 'loadPages'])
        ->and(benchmark(ViewSeriesBench::class)->beforeMethods)->toBeEmpty();
});

it('names the parameter sets of two providers as phpbench does, first provider innermost', function (): void {
    $expected = [
        'hot article,all time',
        'cold article,all time',
        'all articles,all time',
        'hot article,past year',
        'cold article,past year',
        'all articles,past year',
        'hot article,past 30 days',
        'cold article,past 30 days',
        'all articles,past 30 days',
        'hot article,past day',
        'cold article,past day',
        'all articles,past day',
    ];

    expect(setsOf(CountViewsBench::class, 'benchCount'))->toBe($expected)
        ->and(setsOf(CountViewsBench::class, 'benchUniqueCount'))->toBe($expected)
        ->and(benchmark(CountViewsBench::class)->variants)->toHaveCount(24);
});

it('names the parameter sets of the collection benchmarks as those of the plain count', function (): void {
    $expected = setsOf(CountViewsBench::class, 'benchCount');

    expect(setsOf(CountViewsByCollectionBench::class, 'benchCountByCollection'))->toBe($expected)
        ->and(setsOf(CountViewsByCollectionBench::class, 'benchUniqueCountByCollection'))->toBe($expected)
        ->and(benchmark(CountViewsByCollectionBench::class)->variants)->toHaveCount(24);
});

it('names the parameter sets of the collection filter benchmarks as those of the plain count', function (): void {
    $expected = setsOf(CountViewsBench::class, 'benchCount');

    expect(setsOf(CountViewsInCollectionBench::class, 'benchCountInCollection'))->toBe($expected)
        ->and(setsOf(CountViewsInCollectionBench::class, 'benchUniqueCountInCollection'))->toBe($expected)
        ->and(benchmark(CountViewsInCollectionBench::class)->variants)->toHaveCount(24);
});

it('names the parameter sets of the page benchmarks', function (): void {
    $expected = [
        'hot page,all time',
        'cold page,all time',
        'hot page,past year',
        'cold page,past year',
        'hot page,past 30 days',
        'cold page,past 30 days',
        'hot page,past day',
        'cold page,past day',
    ];

    expect(setsOf(CountViewsForViewablesBench::class, 'benchCounts'))->toBe($expected)
        ->and(setsOf(CountViewsForViewablesBench::class, 'benchUniqueCounts'))->toBe($expected)
        ->and(setsOf(CountViewsForViewablesBench::class, 'benchCountLoop'))->toBe($expected);
});

it('names the parameter sets of the long list benchmarks', function (): void {
    $expected = [
        '100 articles,all time',
        '250 articles,all time',
        '1,000 articles,all time',
        '100 articles,past year',
        '250 articles,past year',
        '1,000 articles,past year',
        '100 articles,past 30 days',
        '250 articles,past 30 days',
        '1,000 articles,past 30 days',
        '100 articles,past day',
        '250 articles,past day',
        '1,000 articles,past day',
    ];

    expect(setsOf(CountViewsForViewablesBench::class, 'benchCountsManyKeys'))->toBe($expected)
        ->and(setsOf(CountViewsForViewablesBench::class, 'benchCountLoopManyKeys'))->toBe($expected)
        ->and(benchmark(CountViewsForViewablesBench::class)->variants)->toHaveCount(48);
});

it('names the parameter sets of the interval benchmarks', function (): void {
    $expected = [
        'hot article,7 days by hour',
        'all articles,7 days by hour',
        'hot article,30 days by hour',
        'all articles,30 days by hour',
        'hot article,1 year by day',
        'all articles,1 year by day',
        'hot article,2 years by week',
        'all articles,2 years by week',
        'hot article,2 years by month',
        'all articles,2 years by month',
    ];

    expect(setsOf(CountViewsByIntervalBench::class, 'benchCountByInterval'))->toBe($expected)
        ->and(setsOf(CountViewsByIntervalBench::class, 'benchUniqueCountByInterval'))->toBe($expected)
        ->and(setsOf(CountViewsByIntervalBench::class, 'benchCountByIntervalInTimezone'))->toBe($expected);
});

it('names the parameter sets of the also viewed benchmark, three providers deep', function (): void {
    expect(setsOf(AlsoViewedBench::class, 'benchAlsoViewed'))->toBe([
        'hot article,thousand visitors,all time',
        'cold article,thousand visitors,all time',
        'hot article,every visitor,all time',
        'cold article,every visitor,all time',
        'hot article,thousand visitors,past year',
        'cold article,thousand visitors,past year',
        'hot article,every visitor,past year',
        'cold article,every visitor,past year',
        'hot article,thousand visitors,past 30 days',
        'cold article,thousand visitors,past 30 days',
        'hot article,every visitor,past 30 days',
        'cold article,every visitor,past 30 days',
        'hot article,thousand visitors,past day',
        'cold article,thousand visitors,past day',
        'hot article,every visitor,past day',
        'cold article,every visitor,past day',
    ]);
});

it('names the parameter sets of the ranking benchmark', function (): void {
    $expected = [
        'every type,all time',
        'articles,all time',
        'every type,past year',
        'articles,past year',
        'every type,past 30 days',
        'articles,past 30 days',
        'every type,past day',
        'articles,past day',
    ];

    expect(setsOf(TopViewedBench::class, 'benchTop'))->toBe($expected)
        ->and(setsOf(TopViewedBench::class, 'benchUniqueTop'))->toBe($expected)
        ->and(setsOf(TrendingBench::class, 'benchTrending'))->toBe($expected)
        ->and(setsOf(TrendingBench::class, 'benchUniqueTrending'))->toBe($expected);
});

it('names the parameter sets of the visitor filter benchmarks', function (): void {
    $expected = [
        'returning visitor,all time',
        'new visitor,all time',
        'returning visitor,past year',
        'new visitor,past year',
        'returning visitor,past 30 days',
        'new visitor,past 30 days',
        'returning visitor,past day',
        'new visitor,past day',
    ];

    expect(setsOf(WhereViewedBench::class, 'benchWhereViewedByVisitor'))->toBe($expected)
        ->and(setsOf(WhereViewedBench::class, 'benchWhereNotViewedByVisitor'))->toBe($expected);
});

it('names the parameter sets of the remembered count benchmarks', function (): void {
    $stores = ['array store', 'redis store'];

    expect(setsOf(RememberedCountsBench::class, 'benchRememberedCount'))->toBe([
        'hot article,array store',
        'cold article,array store',
        'all articles,array store',
        'hot article,redis store',
        'cold article,redis store',
        'all articles,redis store',
    ])
        ->and(setsOf(RememberedCountsBench::class, 'benchRememberedCounts'))->toBe($stores)
        ->and(setsOf(RememberedCountsBench::class, 'benchForgetCache'))->toBe($stores)
        ->and(setsOf(RememberedCountsBench::class, 'benchFlushCache'))->toBe($stores);
});

it('names the parameter sets of one provider after its keys', function (): void {
    $expected = ['all time', 'past year', 'past 30 days', 'past day'];

    expect(setsOf(OrderByViewsBench::class, 'benchOrderByViews'))->toBe($expected)
        ->and(setsOf(OrderByViewsBench::class, 'benchOrderByUniqueViews'))->toBe($expected)
        ->and(setsOf(TrendingBench::class, 'benchOrderByTrending'))->toBe($expected)
        ->and(setsOf(TrendingBench::class, 'benchOrderByUniqueTrending'))->toBe($expected)
        ->and(setsOf(RollupReadsBench::class, 'benchTrending'))->toBe($expected)
        ->and(setsOf(WhereViewsCountBench::class, 'benchWhereViewsCount'))->toBe($expected)
        ->and(setsOf(WhereViewsCountBench::class, 'benchWhereUniqueViewsCount'))->toBe($expected)
        ->and(setsOf(WithViewsCountBench::class, 'benchWithViewsCount'))->toBe($expected)
        ->and(setsOf(WithViewsCountBench::class, 'benchWithUniqueViewsCount'))->toBe($expected)
        ->and(setsOf(DestroyViewsBench::class, 'benchDestroy'))->toBe(['100 views', '1,000 views', '10,000 views'])
        ->and(setsOf(CooldownManagerBench::class, 'benchPush'))->toBe(['empty session', '100 cooldowns', '1,000 cooldowns', '10,000 cooldowns'])
        ->and(setsOf(ViewSeriesBench::class, 'benchFill'))->toBe(['168 buckets', '720 buckets', '8,760 buckets']);
});

it('gives a subject without providers one variant with the empty set name', function (): void {
    $variants = benchmark(RecordViewBench::class)->variants;

    expect($variants)->toHaveCount(2)
        ->and(array_map(fn (Variant $variant): string => $variant->subject, $variants))->toBe(['benchRecord', 'benchRecordQueued'])
        ->and(array_map(fn (Variant $variant): string => $variant->set, $variants))->toBe(['', ''])
        ->and($variants[0]->params)->toBeEmpty()
        ->and($variants[0]->title())->toBe('RecordViewBench::benchRecord');
});

it('merges the parameters of the providers in provider order', function (): void {
    $variant = benchmark(CountViewsBench::class)->variants[0];

    expect($variant)
        ->class->toBe(CountViewsBench::class)
        ->subject->toBe('benchCount')
        ->keys->toBe(['hot article', 'all time'])
        ->set->toBe('hot article,all time')
        ->params->toBe(['target' => 'hot', 'days' => null])
        ->benchmark()->toBe('CountViewsBench')
        ->title()->toBe('CountViewsBench::benchCount (hot article, all time)');

    $interval = benchmark(CountViewsByIntervalBench::class)->variants[1];

    expect($interval)
        ->set->toBe('all articles,7 days by hour')
        ->params->toBe(['target' => 'type', 'days' => 7, 'granularity' => 'hour']);
});

it('names the parameter sets of the flush benchmark', function (): void {
    expect(setsOf(BufferViewsBench::class, 'benchFlush'))->toBe(['100 views', '1,000 views', '10,000 views'])
        ->and(setsOf(BufferViewsBench::class, 'benchRecord'))->toBe(['']);
});

it('names the parameter sets of the maintenance benchmarks', function (): void {
    expect(setsOf(AnonymiseViewsBench::class, 'benchAnonymiseDay'))->toBe(['1,000 per chunk', '5,000 per chunk'])
        ->and(setsOf(PruneViewsBench::class, 'benchPruneDay'))->toBe(['1,000 per chunk', '5,000 per chunk'])
        ->and(setsOf(RecountViewsBench::class, 'benchRecountChanged'))->toBe(['10 articles viewed', '100 articles viewed'])
        ->and(benchmark(RecountViewsBench::class)->beforeMethods)->toBe(['setUp', 'begin'])
        ->and(setsOf(DetectSpikesBench::class, 'benchDetect'))->toBe(['spikes', 'spikes and drops'])
        ->and(benchmark(DetectSpikesBench::class)->beforeMethods)->toBe(['setUp', 'begin']);
});

it('names the parameter sets of the growth benchmarks', function (): void {
    expect(setsOf(GrowthBench::class, 'benchRising'))->toBe([
        'every type,past hour',
        'articles,past hour',
        'every type,past day',
        'articles,past day',
    ])
        ->and(setsOf(GrowthBench::class, 'benchAnomalies'))->toBe([
            'every type,past hour,4 weeks',
            'articles,past hour,4 weeks',
            'every type,past day,4 weeks',
            'articles,past day,4 weeks',
            'every type,past hour,8 weeks',
            'articles,past hour,8 weeks',
            'every type,past day,8 weeks',
            'articles,past day,8 weeks',
            'every type,past hour,7 days',
            'articles,past hour,7 days',
            'every type,past day,7 days',
            'articles,past day,7 days',
        ])
        ->and(setsOf(GrowthBench::class, 'benchDrops'))->toBe(['past hour,4 weeks', 'past day,4 weeks', 'past hour,8 weeks', 'past day,8 weeks', 'past hour,7 days', 'past day,7 days'])
        ->and(setsOf(GrowthBench::class, 'benchAgainstBaseline'))->toBe(['hot article,past hour', 'cold article,past hour', 'hot article,past day', 'cold article,past day'])
        ->and(setsOf(RollupReadsBench::class, 'benchAnomalies'))->toBe(['']);
});
