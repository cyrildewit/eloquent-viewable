<?php

declare(strict_types=1);

use CyrildeWit\EloquentViewable\Models\View;
use CyrildeWit\EloquentViewable\Querying\Rollups\Actions\ExpireTiers;
use CyrildeWit\EloquentViewable\Querying\Rollups\Actions\FoldViews;
use CyrildeWit\EloquentViewable\Querying\Rollups\Exceptions\ResolutionUnavailable;
use CyrildeWit\EloquentViewable\Querying\Rollups\Models\ViewRollup;
use CyrildeWit\EloquentViewable\Querying\Rollups\RollupSource;
use CyrildeWit\EloquentViewable\Querying\Sources\SourceManager;
use CyrildeWit\EloquentViewable\Retention\Actions\AnonymiseViews;
use CyrildeWit\EloquentViewable\Retention\Actions\PruneViews;
use CyrildeWit\EloquentViewable\Support\Granularity;
use CyrildeWit\EloquentViewable\Support\Period;
use CyrildeWit\EloquentViewable\Tests\Fixtures\Models\Post;
use CyrildeWit\EloquentViewable\Tests\Fixtures\Models\User;
use Illuminate\Support\Carbon;

/**
 * Two posts viewed from December to today. Every read is taken once from the
 * views table, then again from the rollups once the views before March are
 * folded and deleted.
 */
beforeEach(function (): void {
    $this->travelTo(Carbon::parse('2026-03-31 12:00:00'));

    config()->set('eloquent-viewable.retention.rollups.tiers', ['day' => null, 'month' => null]);
    config()->set('eloquent-viewable.retention.rollups.groupings', ['viewable', 'viewable_collection', 'type', 'type_collection']);

    $this->post = Post::factory()->create();
    $this->other = Post::factory()->create();

    foreach ([
        [$this->post, '2025-12-20 10:00:00', 'visitor-1', null],
        [$this->post, '2026-01-10 10:00:00', 'visitor-1', null],
        [$this->post, '2026-01-10 11:00:00', 'visitor-2', 'featured'],
        [$this->post, '2026-01-25 09:00:00', 'visitor-1', null],
        [$this->post, '2026-02-14 10:00:00', 'visitor-3', null],
        [$this->post, '2026-03-05 10:00:00', 'visitor-1', 'featured'],
        [$this->post, '2026-03-31 09:00:00', 'visitor-4', null],
        [$this->other, '2026-01-10 10:00:00', 'visitor-1', null],
        [$this->other, '2026-02-20 10:00:00', 'visitor-2', null],
    ] as [$post, $viewedAt, $visitor, $collection]) {
        View::factory()->for($post, 'viewable')->viewedAt(Carbon::parse($viewedAt))->fromVisitor($visitor)->inCollection($collection)->create();
    }
});

function readFrom(string $driver): void
{
    config()->set('eloquent-viewable.querying.source.driver', $driver);
}

function foldAndPrune(string $before = '2026-03-01'): void
{
    app(FoldViews::class)->handle();
    app(PruneViews::class)->handle(Carbon::parse($before), 100);
}

/**
 * Every read the rollups have to answer like the views table.
 *
 * @return array<string, mixed>
 */
function reads(Post $post, Post $other): array
{
    $series = static fn (Granularity $granularity, ?string $timezone = null): array => views($post)
        ->period(Period::create('2026-01-01', '2026-04-01'))
        ->timezone($timezone)
        ->countByInterval($granularity)
        ->values();

    return [
        'all time' => views($post)->count(),
        'whole months' => views($post)->period(Period::create('2026-01-01', '2026-03-01'))->count(),
        'days around months' => views($post)->period(Period::create('2026-01-10', '2026-03-20'))->count(),
        'since' => views($post)->period(Period::since('2026-01-25'))->count(),
        'collection' => views($post)->collection('featured')->count(),
        'type' => views(new Post)->count(),
        'type in a collection' => views(new Post)->collection('featured')->count(),
        'monthly' => $series(Granularity::Month),
        'weekly' => $series(Granularity::Week),
        'yearly' => $series(Granularity::Year),
        'by collection' => views($post)->countByCollection(),
        'many' => views(new Post)->forViewables([$post, $other])->counts()->all(),
        'ordered' => Post::query()->orderByViews()->pluck('id')->all(),
        'with count' => Post::query()->withViewsCount()->orderBy('id')->pluck('views_count')->all(),
        'filtered' => Post::query()->whereViewsCount('>', 5)->pluck('id')->all(),
        'top' => views(new Post)->top()->entries->map(fn ($entry): array => [$entry->viewable->getKey(), $entry->count])->all(),
    ];
}

it('is registered as the rollup source driver', function (): void {
    expect(app(SourceManager::class)->driver('rollup'))->toBeInstanceOf(RollupSource::class);
});

it('answers every read like the views table once the views are folded and deleted', function (): void {
    $expected = reads($this->post, $this->other);

    foldAndPrune();
    readFrom('rollup');

    expect(View::query()->count())->toBe(2)
        ->and(reads($this->post, $this->other))->toBe($expected);
});

it('answers like the views table while nothing is deleted', function (): void {
    $expected = reads($this->post, $this->other);

    app(FoldViews::class)->handle();
    readFrom('rollup');

    expect(reads($this->post, $this->other))->toBe($expected);
});

it('reads only the views table while no tier is configured', function (): void {
    config()->set('eloquent-viewable.retention.rollups.tiers', []);
    readFrom('rollup');

    expect(views($this->post)->count())->toBe(7)
        ->and(views($this->post)->countByCollection())->toBe(['' => 5, 'featured' => 2]);
});

it('reads only the views table before the first fold', function (): void {
    readFrom('rollup');

    expect(views($this->post)->count())->toBe(7)
        ->and(views(new Post)->forViewables([$this->post])->counts()->all())->toBe([$this->post->getKey() => 7])
        ->and(views(new Post)->top()->entries->map(fn ($entry): array => [$entry->viewable->getKey(), $entry->count])->all())
        ->toBe([[$this->post->getKey(), 7], [$this->other->getKey(), 2]]);
});

it('reads a series no tier fits from the views table', function (): void {
    $hourly = fn (): array => views($this->post)->period(Period::create('2026-01-10', '2026-01-11'))->countByInterval(Granularity::Hour)->values();
    $expected = $hourly();

    app(FoldViews::class)->handle();
    readFrom('rollup');

    expect($hourly())->toBe($expected);
});

it('reads whether a visitor viewed from the views table', function (): void {
    foldAndPrune();
    readFrom('rollup');

    expect(Post::query()->whereViewedByVisitor('visitor-4')->pluck('id')->all())->toBe([$this->post->getKey()])
        ->and(Post::query()->whereViewedByVisitor('visitor-3')->pluck('id')->all())->toBe([]);
});

it('remembers its counts apart from the views table', function (): void {
    foldAndPrune();

    expect(views($this->post)->remember(3600)->count())->toBe(2);

    readFrom('rollup');

    expect(views($this->post)->remember(3600)->count())->toBe(7);
});

it('reads only what is kept for a viewer', function (): void {
    $user = User::factory()->create();
    View::factory()->for($this->post, 'viewable')->by($user)->viewedAt(Carbon::parse('2026-03-30'))->create();

    foldAndPrune();
    readFrom('rollup');

    expect(views($this->post)->viewedBy($user)->count())->toBe(1);
});

it('reads the views table for a grouping that is not kept', function (): void {
    config()->set('eloquent-viewable.retention.rollups.groupings', ['viewable']);

    foldAndPrune();
    readFrom('rollup');

    expect(views($this->post)->count())->toBe(7)
        ->and(views($this->post)->collection('featured')->count())->toBe(1)
        ->and(views(new Post)->count())->toBe(2);
});

it('sums unique visitors across buckets once the views behind them are deleted', function (): void {
    expect(views($this->post)->unique()->count())->toBe(4);

    foldAndPrune();
    readFrom('rollup');

    expect(views($this->post)->unique()->count())->toBe(2 + 1 + 2 + 1)
        ->and(views($this->post)->unique()->period(Period::create('2026-01-01', '2026-02-01'))->count())->toBe(2);
});

it('reads unique visitors from the views table while it holds them all', function (): void {
    app(FoldViews::class)->handle();
    readFrom('rollup');

    expect(views($this->post)->unique()->count())->toBe(4)
        ->and(views(new Post)->unique()->count())->toBe(4)
        ->and(Post::query()->orderByUniqueViews()->pluck('id')->first())->toBe($this->post->getKey());
});

it('keeps the unique visitors of whole buckets once the views behind them are anonymised', function (): void {
    $january = fn (): int => views($this->post)->unique()->period(Period::create('2026-01-01', '2026-02-01'))->count();
    $monthly = fn (): array => views($this->post)->unique()->period(Period::create('2026-01-01', '2026-03-01'))->countByInterval(Granularity::Month)->values();

    expect($january())->toBe(2)
        ->and($monthly())->toBe([2, 1]);

    app(FoldViews::class)->handle();
    app(AnonymiseViews::class)->handle(Carbon::parse('2026-03-01'), ['visitor', 'viewer', 'context'], 100);

    // The views table now holds one id per visitor per day.
    expect($january())->toBe(3);

    readFrom('rollup');

    expect($january())->toBe(2)
        ->and($monthly())->toBe([2, 1]);
});

it('loses resolution but never a count once a tier expires', function (): void {
    config()->set('eloquent-viewable.retention.rollups.tiers', ['day' => '30d', 'month' => null]);
    config()->set('eloquent-viewable.retention.rollups.strict', true);

    $reads = fn (): array => [
        'all time' => views($this->post)->count(),
        'whole months' => views($this->post)->period(Period::create('2026-01-01', '2026-03-01'))->count(),
        'monthly' => views($this->post)->period(Period::create('2026-01-01', '2026-04-01'))->countByInterval(Granularity::Month)->values(),
        'type' => views(new Post)->count(),
    ];
    $expected = $reads();

    app(FoldViews::class)->handle();
    app(ExpireTiers::class)->handle(chunk: 100);
    app(PruneViews::class)->handle(Carbon::parse('2026-03-01'), 100);
    readFrom('rollup');

    expect(ViewRollup::query()->where('tier', 'day')->where('bucket_start', '<', '2026-03-01')->count())->toBe(0)
        ->and($reads())->toBe($expected)
        ->and(fn (): int => views($this->post)->period(Period::create('2026-01-10', '2026-03-20'))->count())
        ->toThrow(ResolutionUnavailable::class, 'The period starts or ends inside a rollup bucket');
});

it('ranks by unique visitors summed across the views table and the rollups', function (): void {
    foldAndPrune();
    readFrom('rollup');

    expect(views(new Post)->unique()->top()->entries->map(fn ($entry): array => [$entry->viewable->getKey(), $entry->count])->all())
        ->toBe([[$this->post->getKey(), 6], [$this->other->getKey(), 2]]);
});

it('counts a bucket when its start lies inside a period that cuts through it', function (): void {
    foldAndPrune();
    readFrom('rollup');

    expect(views($this->post)->period(Period::create('2026-01-10 10:30:00', '2026-01-25 08:00:00'))->count())->toBe(1);
});

it('places a bucket by its label in a series in another timezone', function (): void {
    $expected = views($this->post)->period(Period::create('2026-01-01', '2026-03-01'))->timezone('America/New_York')->countByInterval(Granularity::Month)->values();

    foldAndPrune();
    readFrom('rollup');

    expect(views($this->post)->period(Period::create('2026-01-01', '2026-03-01'))->timezone('America/New_York')->countByInterval(Granularity::Month)->values())
        ->toBe($expected)
        ->toBe([0, 3, 1]);
});

it('converts an hour bucket into a series in another timezone', function (): void {
    config()->set('eloquent-viewable.retention.rollups.tiers', ['hour' => null, 'day' => null, 'month' => null]);

    $expected = views($this->post)->period(Period::create('2026-01-10', '2026-01-11'))->timezone('Asia/Kolkata')->countByInterval(Granularity::Hour)->values();

    foldAndPrune();
    readFrom('rollup');

    expect(views($this->post)->period(Period::create('2026-01-10', '2026-01-11'))->timezone('Asia/Kolkata')->countByInterval(Granularity::Hour)->values())
        ->toBe($expected);
});

it('builds an hour series in a zone whole hours apart in strict mode', function (): void {
    config()->set('eloquent-viewable.retention.rollups.tiers', ['hour' => null, 'day' => null, 'month' => null]);
    config()->set('eloquent-viewable.retention.rollups.strict', true);

    $hourly = fn (string $timezone): array => views($this->post)->period(Period::create('2026-01-10', '2026-01-11'))->timezone($timezone)->countByInterval(Granularity::Hour)->values();
    $expected = $hourly('America/New_York');

    foldAndPrune();
    readFrom('rollup');

    expect($hourly('America/New_York'))->toBe($expected)
        ->and(fn (): array => $hourly('Asia/Kolkata'))
        ->toThrow(ResolutionUnavailable::class, 'A series in `Asia/Kolkata` cannot be built exactly from rollup buckets aligned to `UTC`.');
});

describe('strict', function (): void {
    beforeEach(function (): void {
        config()->set('eloquent-viewable.retention.rollups.strict', true);

        foldAndPrune();
        readFrom('rollup');
    });

    it('answers what the rollups hold exactly', function (): void {
        expect(views($this->post)->period(Period::create('2026-01-01', '2026-03-01'))->count())->toBe(4)
            ->and(views($this->post)->unique()->period(Period::create('2026-01-01', '2026-02-01'))->count())->toBe(2)
            ->and(views($this->post)->unique()->period(Period::create('2026-01-01', '2026-03-01'))->countByInterval(Granularity::Month)->values())->toBe([2, 1]);
    });

    it('refuses a period that cuts through a bucket', function (): void {
        views($this->post)->period(Period::create('2026-01-10 10:30:00', '2026-02-01'))->count();
    })->throws(ResolutionUnavailable::class, 'The period starts or ends inside a rollup bucket');

    it('refuses to sum unique visitors', function (): void {
        views($this->post)->unique()->count();
    })->throws(ResolutionUnavailable::class, 'Unique visitors over this period would be summed');

    it('refuses to sum unique visitors into a coarser series', function (): void {
        config()->set('eloquent-viewable.retention.rollups.tiers', ['day' => null]);

        views($this->post)->unique()->period(Period::create('2026-01-01', '2026-03-01'))->countByInterval(Granularity::Month);
    })->throws(ResolutionUnavailable::class, 'Unique visitors over this period would be summed');

    it('refuses to sum unique visitors across the hand-over to the views table', function (): void {
        views($this->post)->unique()->period(Period::create('2026-02-01', '2026-04-01'))->countByInterval(Granularity::Year);
    })->throws(ResolutionUnavailable::class, 'Unique visitors over this period would be summed');

    it('refuses a series in another timezone', function (): void {
        views($this->post)->period(Period::create('2026-01-01', '2026-03-01'))->timezone('America/New_York')->countByInterval(Granularity::Month);
    })->throws(ResolutionUnavailable::class, 'A series in `America/New_York` cannot be built exactly from rollup buckets aligned to `UTC`.');
});
