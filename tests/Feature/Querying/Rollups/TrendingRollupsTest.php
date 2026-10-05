<?php

declare(strict_types=1);

use CyrildeWit\EloquentViewable\Models\View;
use CyrildeWit\EloquentViewable\Querying\Ranking\Entry;
use CyrildeWit\EloquentViewable\Querying\Rollups\Actions\FoldViews;
use CyrildeWit\EloquentViewable\Querying\Rollups\Exceptions\ResolutionUnavailable;
use CyrildeWit\EloquentViewable\Retention\Actions\PruneViews;
use CyrildeWit\EloquentViewable\Support\Period;
use CyrildeWit\EloquentViewable\Tests\Fixtures\Models\Post;
use Illuminate\Support\Carbon;

/**
 * Three posts viewed over the past week. Every trending read is taken once
 * from the views table, then again from the rollups once the views before
 * October 3 are folded into hours and days and deleted.
 */
beforeEach(function (): void {
    $this->travelTo(Carbon::parse('2026-10-04 12:30:00'));

    config()->set('eloquent-viewable.retention.rollups.tiers', ['hour' => null, 'day' => null]);
    config()->set('eloquent-viewable.retention.rollups.groupings', ['viewable', 'viewable_collection', 'type', 'type_collection']);

    $this->first = Post::factory()->create();
    $this->second = Post::factory()->create();
    $this->third = Post::factory()->create();

    foreach ([
        [$this->first, '2026-09-30 10:15:00', ['visitor-1', 'visitor-1', 'visitor-2', 'visitor-3', 'visitor-3', 'visitor-3'], null],
        [$this->first, '2026-10-04 11:40:00', ['visitor-1', 'visitor-4'], null],
        [$this->second, '2026-10-02 09:05:00', ['visitor-1', 'visitor-2'], null],
        [$this->second, '2026-10-02 09:50:00', ['visitor-3'], 'amp'],
        [$this->second, '2026-10-03 20:00:00', ['visitor-5', 'visitor-5', 'visitor-5', 'visitor-5'], null],
        [$this->third, '2026-10-04 12:10:00', ['visitor-6'], 'amp'],
    ] as [$post, $viewedAt, $visitors, $collection]) {
        foreach ($visitors as $visitor) {
            View::factory()->for($post, 'viewable')->viewedAt(Carbon::parse($viewedAt))->fromVisitor($visitor)->inCollection($collection)->create();
        }
    }
});

function trendFrom(string $driver): void
{
    config()->set('eloquent-viewable.querying.source.driver', $driver);
}

function foldAndPruneTrending(): void
{
    app(FoldViews::class)->handle();
    app(PruneViews::class)->handle(Carbon::parse('2026-10-03'), 100);
}

/**
 * Every trending read the rollups have to answer like the views table.
 *
 * @return array<string, mixed>
 */
function trendingReads(): array
{
    $ranked = static fn ($ranking): array => array_values($ranking->entries->map(static fn (Entry $entry): array => [$entry->viewable->getKey(), $entry->count, $entry->score])->all());
    $scores = static fn ($query): array => array_map(floatval(...), $query->orderBy('id')->pluck('trending_score', 'id')->all());

    return [
        'ranking' => $ranked(views(Post::class)->trending()),
        'unique' => $ranked(views(Post::class)->unique()->trending()),
        'past period' => $ranked(views(Post::class)->period(Period::create('2026-09-30', '2026-10-03'))->trending()),
        'collection' => $ranked(views(Post::class)->collection('amp')->trending()),
        'limited' => $ranked(views(Post::class)->trending(2)),
        'scores' => $scores(Post::query()->withTrendingScore()),
        'unique scores' => $scores(Post::query()->withTrendingScore(unique: true)),
        'collection scores' => $scores(Post::query()->withTrendingScore(collection: 'amp')),
        'ordered' => Post::query()->orderByTrending()->pluck('id')->all(),
    ];
}

it('scores like the views table once the views are folded and deleted', function (): void {
    $expected = trendingReads();

    foldAndPruneTrending();
    trendFrom('rollup');

    expect(View::query()->count())->toBe(7)
        ->and(trendingReads())->toBe($expected);
});

it('scores per day like the views table', function (): void {
    config()->set('eloquent-viewable.querying.trending.step', '1d');
    $expected = trendingReads();

    foldAndPruneTrending();
    trendFrom('rollup');

    expect(trendingReads())->toBe($expected);
});

it('scores like the views table before the first fold', function (): void {
    $expected = trendingReads();

    trendFrom('rollup');

    expect(trendingReads())->toBe($expected);
});

it('reads the views table when no tier is as fine as the step', function (): void {
    config()->set('eloquent-viewable.retention.rollups.tiers', ['day' => null]);

    foldAndPruneTrending();
    $expected = trendingReads();
    trendFrom('rollup');

    expect(trendingReads())->toBe($expected)
        ->and(views(Post::class)->trending()->viewables()->modelKeys())->toBe(keysOf($this->second, $this->first, $this->third)->all());
});

it('reads unique visitors only from the tier of the step', function (): void {
    config()->set('eloquent-viewable.querying.trending.step', '1d');
    config()->set('eloquent-viewable.retention.rollups.tiers', ['hour' => null]);

    foldAndPruneTrending();
    $expected = views(Post::class)->unique()->trending()->toArray();
    trendFrom('rollup');

    expect(views(Post::class)->unique()->trending()->toArray())->toBe($expected);
});

it('reads the views table while no tier is configured', function (): void {
    $expected = trendingReads();

    config()->set('eloquent-viewable.retention.rollups.tiers', []);
    trendFrom('rollup');

    expect(trendingReads())->toBe($expected);
});

describe('strict', function (): void {
    beforeEach(function (): void {
        config()->set('eloquent-viewable.retention.rollups.strict', true);
    });

    it('answers what the rollups hold exactly', function (): void {
        $expected = trendingReads();

        foldAndPruneTrending();
        trendFrom('rollup');

        expect(trendingReads())->toBe($expected);
    });

    it('refuses a step no tier is as fine as', function (): void {
        config()->set('eloquent-viewable.retention.rollups.tiers', ['day' => null]);
        foldAndPruneTrending();
        trendFrom('rollup');

        views(Post::class)->trending();
    })->throws(ResolutionUnavailable::class, 'No rollup tier is as fine as the trending step of one hour');

    it('refuses a period that cuts through a bucket', function (): void {
        foldAndPruneTrending();
        trendFrom('rollup');

        Post::query()->withTrendingScore(Period::create('2026-09-30 10:30:00', '2026-10-03'))->get();
    })->throws(ResolutionUnavailable::class, 'The period starts or ends inside a rollup bucket');
});
