<?php

declare(strict_types=1);

use CyrildeWit\EloquentViewable\Dimensions\Device;
use CyrildeWit\EloquentViewable\Dimensions\Normaliser;
use CyrildeWit\EloquentViewable\Dimensions\Source;
use CyrildeWit\EloquentViewable\Exceptions\InvalidConfiguration;
use CyrildeWit\EloquentViewable\Models\View;
use CyrildeWit\EloquentViewable\Querying\Dimensions\DimensionCounts;
use CyrildeWit\EloquentViewable\Querying\Exceptions\UnsupportedBySource;
use CyrildeWit\EloquentViewable\Querying\Rollups\Actions\FoldViews;
use CyrildeWit\EloquentViewable\Querying\Rollups\Exceptions\ResolutionUnavailable;
use CyrildeWit\EloquentViewable\Querying\Rollups\Models\ViewRollup;
use CyrildeWit\EloquentViewable\Querying\Rollups\RollupPolicy;
use CyrildeWit\EloquentViewable\Retention\Actions\PruneViews;
use CyrildeWit\EloquentViewable\Support\Granularity;
use CyrildeWit\EloquentViewable\Support\Period;
use CyrildeWit\EloquentViewable\Tests\Fixtures\Dimensions\PlanDimension;
use CyrildeWit\EloquentViewable\Tests\Fixtures\Models\Post;
use CyrildeWit\EloquentViewable\Tests\Fixtures\Models\User;
use CyrildeWit\EloquentViewable\Tests\Fixtures\Rollups\NewsletterViews;
use CyrildeWit\EloquentViewable\Views;
use Illuminate\Support\Carbon;

/**
 * One post viewed in January, February and March. The source keeps two values
 * per bucket, so on the tenth of January `Direct` and `X` fold into `other`,
 * both seen by `visitor-5`.
 */
beforeEach(function (): void {
    $this->travelTo(Carbon::parse('2026-03-31 12:00:00'));

    config()->set('eloquent-viewable.querying.source.driver', 'rollup');
    config()->set('eloquent-viewable.retention.rollups.tiers', ['day' => null, 'month' => null]);
    config()->set('eloquent-viewable.retention.rollups.groupings', ['viewable', 'viewable_collection', 'type']);
    config()->set('eloquent-viewable.dimensions.definitions', [
        'source' => [Source::class, 'maxValues' => 2],
        'device' => Device::class,
        'plan' => [PlanDimension::class, 'maxValues' => null],
    ]);
    config()->set('eloquent-viewable.retention.rollups.dimensions', ['source', 'plan']);

    $this->post = Post::factory()->create();
    $this->other = Post::factory()->create();

    foreach ([
        ['2026-01-10 09:00:00', 'visitor-1', 'Google', 'pro'],
        ['2026-01-10 09:10:00', 'visitor-2', 'Google', 'pro'],
        ['2026-01-10 09:20:00', 'visitor-3', 'Google', null],
        ['2026-01-10 09:30:00', 'visitor-1', 'Bing', 'free'],
        ['2026-01-10 09:40:00', 'visitor-4', 'Bing', null],
        ['2026-01-10 09:50:00', 'visitor-5', 'Direct', null],
        ['2026-01-10 10:00:00', 'visitor-5', 'X', null],
        ['2026-01-10 10:10:00', 'visitor-6', null, null],
        ['2026-02-14 10:00:00', 'visitor-7', 'Google', 'pro'],
        ['2026-02-14 11:00:00', 'visitor-1', 'Reddit', null],
        ['2026-03-20 10:00:00', 'visitor-8', 'Google', null],
        ['2026-03-20 11:00:00', 'visitor-9', 'Bing', null],
    ] as [$viewedAt, $visitor, $source, $plan]) {
        View::factory()
            ->for($this->post, 'viewable')
            ->viewedAt(Carbon::parse($viewedAt))
            ->fromVisitor($visitor)
            ->withDimensions(['source' => $source, 'device' => 'mobile'])
            ->withContext($plan === null ? null : ['plan' => $plan])
            ->create();
    }

    View::factory()->for($this->other, 'viewable')->viewedAt(Carbon::parse('2026-01-10 12:00:00'))->withDimensions(['source' => 'Bing'])->create();
});

function foldAndPruneDimensions(): void
{
    app(FoldViews::class)->handle();
    app(PruneViews::class)->handle(Carbon::parse('2026-03-01'), 100);
}

/** @return array<string, array{int, int}> */
function sourceRows(string $tier, string $bucket, string $grouping = 'viewable:dimension'): array
{
    $rows = [];

    foreach (ViewRollup::query()->where('rollup', 'views:source')->where('tier', $tier)->where('grouping', $grouping)->where('bucket_start', Carbon::parse($bucket))->where('viewable_id', test()->post->getKey())->get() as $row) {
        $rows[(string) $row->getAttribute('dimension')] = [(int) $row->getAttribute('views'), (int) $row->getAttribute('unique_visitors')];
    }

    ksort($rows);

    return $rows;
}

describe('folding', function (): void {
    it('keeps only the rows per value of a dimension', function (): void {
        app(FoldViews::class)->handle();

        $groupings = ViewRollup::query()->where('rollup', 'views:source')->distinct()->pluck('grouping')->sort()->values()->all();

        expect($groupings)->toBe(['type:dimension', 'viewable:dimension', 'viewable_collection:dimension'])
            ->and(ViewRollup::query()->where('rollup', 'views')->where('grouping', 'viewable')->exists())->toBeTrue();
    });

    it('keeps the top values of each bucket and folds the rest into other with exact unique visitors', function (): void {
        app(FoldViews::class)->handle();

        expect(sourceRows('day', '2026-01-10'))->toBe([
            '' => [1, 1],
            Normaliser::Other => [2, 1],
            'Bing' => [2, 2],
            'Google' => [3, 3],
        ])->and(sourceRows('month', '2026-01-01'))->toBe([
            '' => [1, 1],
            Normaliser::Other => [2, 1],
            'Bing' => [2, 2],
            'Google' => [3, 3],
        ])->and(sourceRows('day', '2026-02-14'))->toBe([
            'Google' => [1, 1],
            'Reddit' => [1, 1],
        ]);
    });

    it('keeps every value of a dimension without a cap, from a JSON path', function (): void {
        app(FoldViews::class)->handle();

        $plans = ViewRollup::query()
            ->where('rollup', 'views:plan')
            ->where('tier', 'month')
            ->where('grouping', 'viewable:dimension')
            ->where('viewable_id', $this->post->getKey())
            ->where('bucket_start', Carbon::parse('2026-01-01'))
            ->pluck('views', 'dimension')
            ->map(fn (mixed $views): int => (int) $views)
            ->sortKeys()
            ->all();

        expect($plans)->toBe(['' => 5, 'free' => 1, 'pro' => 2]);
    });

    it('folds only the rollup it is told to', function (): void {
        $this->artisan('views:rollup', ['--rollup' => 'views:source'])->assertSuccessful();

        expect(ViewRollup::query()->distinct()->pluck('rollup')->all())->toBe(['views:source']);
    });

    it('folds a dimension added later from the oldest view still there', function (): void {
        config()->set('eloquent-viewable.retention.rollups.dimensions', []);

        app(FoldViews::class)->handle();

        expect(ViewRollup::query()->where('rollup', 'views:source')->exists())->toBeFalse();

        config()->set('eloquent-viewable.retention.rollups.dimensions', ['source']);

        app(FoldViews::class)->handle();

        expect(sourceRows('month', '2026-01-01'))->toHaveKeys(['Google', 'Bing']);
    });

    it('folds a bucket again from where it is told', function (): void {
        app(FoldViews::class)->handle();

        View::query()->where('viewed_at', Carbon::parse('2026-01-10 09:00:00'))->delete();

        $this->artisan('views:rollup', ['--from' => '2026-01-01'])->assertSuccessful();

        expect(sourceRows('day', '2026-01-10')['Google'])->toBe([2, 2]);
    });
});

describe('reading', function (): void {
    it('counts by a dimension from its rollup and the views table together', function (): void {
        foldAndPruneDimensions();

        $counts = views($this->post)->countBy('source');

        expect($counts->all())->toBe(['Google' => 5, 'Bing' => 3, 'Reddit' => 1])
            ->and($counts->other())->toBe(2)
            ->and($counts->none())->toBe(1)
            ->and($counts->total())->toBe(12)
            ->and(views($this->post)->countBy('source', limit: 1)->toArray())
            ->toBe(['values' => ['Google' => 5], 'none' => 1, 'other' => 6, 'total' => 12]);
    });

    it('counts a dimension kept in context from its rollup', function (): void {
        foldAndPruneDimensions();

        expect(views($this->post)->countBy('plan')->all())->toBe(['pro' => 3, 'free' => 1]);
    });

    it('counts unique visitors per value, with the total read on its own', function (): void {
        foldAndPruneDimensions();

        $counts = views($this->post)->unique()->countBy('source');

        expect($counts->get('Google'))->toBe(5)
            ->and($counts->total())->toBe(views($this->post)->unique()->count());
    });

    it('narrows every read by one value of a folded dimension', function (): void {
        foldAndPruneDimensions();

        $google = fn (): Views => views($this->post)->whereDimension('source', 'Google');

        expect($google()->count())->toBe(5)
            ->and($google()->unique()->count())->toBe(5)
            ->and(views($this->post)->whereDimension('source', ['Google', 'Bing'])->count())->toBe(8)
            ->and($google()->period(Period::create('2026-01-01', '2026-04-01'))->countByInterval(Granularity::Month)->values())->toBe([3, 1, 1])
            ->and($google()->countByCollection())->toBe(['' => 5])
            ->and(views(Post::class)->whereDimension('source', 'Bing')->top()->entries->map(fn ($entry): array => [$entry->viewable->getKey(), $entry->count])->all())
            ->toBe([[$this->post->getKey(), 3], [$this->other->getKey(), 1]])
            ->and(views($this->post)->whereDimension('source', 'Google')->countBy('source')->all())->toBe(['Google' => 5]);
    });

    it('reads a combination the rollups cannot answer from the views table while it is complete', function (): void {
        app(FoldViews::class)->handle();

        expect(views($this->post)->countBy('device')->all())->toBe(['mobile' => 12])
            ->and(views($this->post)->whereDimension('source', 'Google')->whereDimension('plan', 'pro')->count())->toBe(3)
            ->and(views($this->post)->whereDimension('source', ['Google', 'Bing'])->unique()->count())->toBe(7);
    });

    it('refuses a combination the rollups cannot answer once views are pruned', function (Closure $read): void {
        foldAndPruneDimensions();

        $read($this->post);
    })->with([
        'a dimension not folded' => [fn (Post $post): DimensionCounts => views($post)->countBy('device')],
        'two dimensions' => [fn (Post $post): int => views($post)->whereDimension('source', 'Google')->whereDimension('plan', 'pro')->count()],
        'unique visitors across values' => [fn (Post $post): int => views($post)->whereDimension('source', ['Google', 'Bing'])->unique()->count()],
        'a narrowed count by another dimension' => [fn (Post $post): DimensionCounts => views($post)->whereDimension('plan', 'pro')->countBy('source')],
    ])->throws(UnsupportedBySource::class, 'no longer holds the views before 2026-03-01 00:00:00');

    it('refuses such a combination in strict mode with the reason', function (): void {
        config()->set('eloquent-viewable.retention.rollups.strict', true);
        foldAndPruneDimensions();

        views($this->post)->countBy('device');
    })->throws(ResolutionUnavailable::class, 'list `device` under `retention.rollups.dimensions`');

    it('names both dimensions when two are combined', function (): void {
        foldAndPruneDimensions();

        views($this->post)->whereDimension('source', 'Google')->countBy('plan');
    })->throws(UnsupportedBySource::class, 'A count by `source` and `plan` reads the views table');

    it('reads a dimension through the views table when the read is narrowed another way', function (): void {
        config()->set('eloquent-viewable.retention.rollups.custom', [NewsletterViews::class]);
        $viewer = User::factory()->create();
        View::factory()->for($this->post, 'viewable')->by($viewer)->withDimensions(['source' => 'Google'])->inCollection('amp')->withContext(['source' => 'newsletter'])->create();

        app(FoldViews::class)->handle();

        expect(views($this->post)->rollup('newsletter')->whereDimension('source', 'Google')->count())->toBe(1)
            ->and(views($this->post)->viewedBy($viewer)->whereDimension('source', 'Google')->count())->toBe(1)
            ->and(views(Post::class)->collection('amp')->whereDimension('source', 'Google')->count())->toBe(1);
    });

    it('reads such a combination over what the views table still holds', function (): void {
        foldAndPruneDimensions();

        expect(views($this->post)->period(Period::since('2026-03-01'))->countBy('device')->all())->toBe(['mobile' => 2]);
    });
});

describe('config', function (): void {
    it('refuses a dimension that is not listed', function (): void {
        config()->set('eloquent-viewable.retention.rollups.dimensions', ['browser']);

        app(RollupPolicy::class);
    })->throws(InvalidConfiguration::class, 'names `browser`, which is not listed in `dimensions.definitions`');

    it('refuses dimensions without a tier to fold them into', function (): void {
        config()->set('eloquent-viewable.retention.rollups.tiers', []);

        app(RollupPolicy::class);
    })->throws(InvalidConfiguration::class, 'keeps no tier to fold them into');

    it('names the rollup of a dimension after it', function (): void {
        $policy = app(RollupPolicy::class);

        expect($policy->forDimension('source')?->name)->toBe('views:source')
            ->and($policy->forDimension('source')?->maxValues())->toBe(2)
            ->and($policy->forDimension('plan')?->dimension())->toBe('context->plan')
            ->and($policy->forDimension('device'))->toBeNull();
    });
});
