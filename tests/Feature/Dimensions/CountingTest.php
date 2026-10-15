<?php

declare(strict_types=1);

use Carbon\Carbon;
use CyrildeWit\EloquentViewable\Data\ViewRecord;
use CyrildeWit\EloquentViewable\Dimensions\Device;
use CyrildeWit\EloquentViewable\Dimensions\Exceptions\UnknownDimension;
use CyrildeWit\EloquentViewable\Dimensions\Source;
use CyrildeWit\EloquentViewable\Facades\Views;
use CyrildeWit\EloquentViewable\Models\View;
use CyrildeWit\EloquentViewable\Querying\Dimensions\DimensionCounts;
use CyrildeWit\EloquentViewable\Querying\Exceptions\InvalidReturning;
use CyrildeWit\EloquentViewable\Support\DimensionFilter;
use CyrildeWit\EloquentViewable\Support\Granularity;
use CyrildeWit\EloquentViewable\Support\Period;
use CyrildeWit\EloquentViewable\Support\ViewsQuery;
use CyrildeWit\EloquentViewable\Tests\Fixtures\Dimensions\PlanDimension;
use CyrildeWit\EloquentViewable\Tests\Fixtures\Models\Apartment;
use CyrildeWit\EloquentViewable\Tests\Fixtures\Models\Post;

/**
 * @param  array<string, ?string>  $dimensions
 * @param  array<string, mixed>|null  $context
 */
function viewWith(Post|Apartment $viewable, array $dimensions = [], ?string $visitor = null, ?array $context = null, ?string $collection = null, ?Carbon $at = null): void
{
    View::factory()
        ->for($viewable, 'viewable')
        ->withDimensions($dimensions)
        ->withContext($context)
        ->inCollection($collection)
        ->fromVisitor($visitor ?? fake()->uuid())
        ->viewedAt($at ?? Carbon::now())
        ->create();
}

beforeEach(function (): void {
    config()->set('eloquent-viewable.dimensions.definitions', [
        'source' => Source::class,
        'device' => Device::class,
        'plan' => PlanDimension::class,
    ]);

    $this->post = Post::factory()->create();
    $this->other = Post::factory()->create();
});

describe('countBy', function (): void {
    it('counts the views per value, most first', function (): void {
        viewWith($this->post, ['source' => 'Google']);
        viewWith($this->post, ['source' => 'Google']);
        viewWith($this->post, ['source' => 'Bing']);
        viewWith($this->post, ['source' => 'Direct']);
        viewWith($this->post);
        viewWith($this->other, ['source' => 'Google']);

        $counts = views($this->post)->countBy('source');

        expect($counts)->toBeInstanceOf(DimensionCounts::class)
            ->and($counts->all())->toBe(['Google' => 2, 'Bing' => 1, 'Direct' => 1])
            ->and($counts->none())->toBe(1)
            ->and($counts->other())->toBe(0)
            ->and($counts->total())->toBe(5)
            ->and($counts->share('Google'))->toBe(0.4);
    });

    it('keeps the top values and counts the rest in other', function (): void {
        viewWith($this->post, ['source' => 'Google']);
        viewWith($this->post, ['source' => 'Google']);
        viewWith($this->post, ['source' => 'Bing']);
        viewWith($this->post, ['source' => 'Direct']);
        viewWith($this->post, ['source' => 'X']);
        viewWith($this->post);

        $counts = views($this->post)->countBy('source', limit: 2);

        expect($counts->all())->toBe(['Google' => 2, 'Bing' => 1])
            ->and($counts->other())->toBe(2)
            ->and($counts->none())->toBe(1)
            ->and($counts->total())->toBe(6);
    });

    it('counts unique visitors per value, and exactly in other', function (): void {
        viewWith($this->post, ['source' => 'Google'], 'one');
        viewWith($this->post, ['source' => 'Google'], 'one');
        viewWith($this->post, ['source' => 'Google'], 'two');
        viewWith($this->post, ['source' => 'Bing'], 'one');
        viewWith($this->post, ['source' => 'Direct'], 'three');
        viewWith($this->post, ['source' => 'X'], 'three');
        viewWith($this->post, [], 'four');

        $counts = views($this->post)->unique()->countBy('source', limit: 1);

        expect($counts->all())->toBe(['Google' => 2])
            ->and($counts->other())->toBe(2)
            ->and($counts->none())->toBe(1)
            ->and($counts->total())->toBe(4)
            ->and(views($this->post)->unique()->countBy('source')->other())->toBe(0);
    });

    it('counts every model of a type', function (): void {
        viewWith($this->post, ['device' => 'mobile']);
        viewWith($this->other, ['device' => 'mobile']);
        viewWith($this->other, ['device' => 'desktop']);
        viewWith(Apartment::factory()->create(), ['device' => 'tablet']);

        expect(views(Post::class)->countBy('device')->all())->toBe(['mobile' => 2, 'desktop' => 1]);
    });

    it('counts within the period and the collection', function (): void {
        Carbon::setTestNow('2026-10-08 12:00:00');

        viewWith($this->post, ['source' => 'Google'], at: Carbon::parse('2026-09-01'));
        viewWith($this->post, ['source' => 'Bing']);
        viewWith($this->post, ['source' => 'Bing'], collection: 'amp');

        expect(views($this->post)->period(Period::pastDays(7))->countBy('source')->all())->toBe(['Bing' => 2])
            ->and(views($this->post)->collection('amp')->countBy('source')->all())->toBe(['Bing' => 1]);
    });

    it('counts a dimension kept in context', function (): void {
        viewWith($this->post, context: ['plan' => 'pro']);
        viewWith($this->post, context: ['plan' => 'pro', 'other' => 'x']);
        viewWith($this->post, context: ['plan' => 'free']);
        viewWith($this->post, context: ['other' => 'x']);
        viewWith($this->post);

        $counts = views($this->post)->countBy('plan');

        expect($counts->all())->toBe(['pro' => 2, 'free' => 1])
            ->and($counts->none())->toBe(2);
    });

    it('remembers the counts', function (): void {
        viewWith($this->post, ['source' => 'Google']);

        $first = views($this->post)->remember(60)->countBy('source');

        viewWith($this->post, ['source' => 'Google']);

        expect(views($this->post)->remember(60)->countBy('source')->toArray())->toBe($first->toArray())
            ->and(views($this->post)->countBy('source')->get('Google'))->toBe(2);
    });

    it('refuses a dimension that is not in config', function (): void {
        views($this->post)->countBy('browser');
    })->throws(UnknownDimension::class, 'No dimension is named `browser`.');

    it('refuses to count returning visitors by a dimension', function (): void {
        views($this->post)->returning()->countBy('source');
    })->throws(InvalidReturning::class);
});

describe('whereDimension', function (): void {
    beforeEach(function (): void {
        Carbon::setTestNow('2026-10-08 12:00:00');

        viewWith($this->post, ['source' => 'Google', 'device' => 'mobile'], 'one', ['plan' => 'pro']);
        viewWith($this->post, ['source' => 'Google', 'device' => 'desktop'], 'one', ['plan' => 'free']);
        viewWith($this->post, ['source' => 'Google', 'device' => 'mobile'], 'two', collection: 'amp', at: Carbon::parse('2026-10-01 09:00:00'));
        viewWith($this->post, ['source' => 'Bing', 'device' => 'mobile'], 'three');
        viewWith($this->post, ['source' => 'Direct', 'device' => 'desktop'], 'four');
        viewWith($this->other, ['source' => 'Google', 'device' => 'mobile'], 'one');
    });

    it('counts only the views with the value', function (): void {
        expect(views($this->post)->whereDimension('source', 'Google')->count())->toBe(3)
            ->and(views($this->post)->whereDimension('source', 'Google')->unique()->count())->toBe(2);
    });

    it('counts the views with any of the values', function (): void {
        expect(views($this->post)->whereDimension('source', ['Google', 'Bing'])->count())->toBe(4)
            ->and(views($this->post)->whereDimension('source', [])->count())->toBe(0);
    });

    it('narrows further with every call', function (): void {
        expect(views($this->post)->whereDimension('source', 'Google')->whereDimension('device', 'mobile')->count())->toBe(2)
            ->and(views($this->post)->whereDimension('source', 'Google')->whereDimension('source', 'Bing')->count())->toBe(0);
    });

    it('narrows by a dimension kept in context', function (): void {
        expect(views($this->post)->whereDimension('plan', 'pro')->count())->toBe(1);
    });

    it('narrows a count by another dimension', function (): void {
        expect(views($this->post)->whereDimension('device', 'mobile')->countBy('source')->all())->toBe(['Google' => 2, 'Bing' => 1])
            ->and(views($this->post)->whereDimension('source', 'Google')->countBy('plan')->all())->toBe(['free' => 1, 'pro' => 1]);
    });

    it('narrows a comparison, a series and a count per collection', function (): void {
        $google = fn (): CyrildeWit\EloquentViewable\Views => views($this->post)->whereDimension('source', 'Google');
        $series = $google()->period(Period::create('2026-10-01', '2026-10-09'))->countByInterval(Granularity::Day);

        expect($google()->period(Period::pastDays(3))->compare()->current)->toBe(2)
            ->and($google()->period(Period::pastDays(3))->compare()->previous)->toBe(0)
            ->and($series->total())->toBe(3)
            ->and($series->values()[0])->toBe(1)
            ->and($series->values()[7])->toBe(2)
            ->and($google()->countByCollection())->toBe(['' => 2, 'amp' => 1]);
    });

    it('narrows the rankings', function (): void {
        $top = views(Post::class)->whereDimension('source', 'Bing')->top();
        $trending = views(Post::class)->whereDimension('device', 'desktop')->trending();

        expect($top->count())->toBe(1)
            ->and($top->entries->first()?->viewable->is($this->post))->toBeTrue()
            ->and($top->entries->first()?->count)->toBe(1)
            ->and($trending->count())->toBe(1)
            ->and($trending->entries->first()?->count)->toBe(2);
    });

    it('remembers a narrowed count apart from the plain one', function (): void {
        expect(views($this->post)->remember(60)->count())->toBe(5)
            ->and(views($this->post)->remember(60)->whereDimension('source', 'Bing')->count())->toBe(1)
            ->and(views($this->post)->remember(60)->whereDimension('source', 'Google')->count())->toBe(3);
    });

    it('refuses a dimension that is not in config', function (): void {
        views($this->post)->whereDimension('browser', 'Firefox');
    })->throws(UnknownDimension::class);

    it('reads the views table through the rollup source', function (): void {
        config()->set('eloquent-viewable.querying.source.driver', 'rollup');
        config()->set('eloquent-viewable.retention.rollups.tiers', ['day' => null]);
        config()->set('eloquent-viewable.retention.rollups.settle');

        Carbon::setTestNow('2026-10-10 12:00:00');

        $this->artisan('views:rollup')->assertSuccessful();

        expect(views($this->post)->count())->toBe(5)
            ->and(views($this->post)->whereDimension('source', 'Google')->count())->toBe(3)
            ->and(views($this->post)->countBy('source', limit: 1)->all())->toBe(['Google' => 3]);
    });
});

describe('the fake', function (): void {
    it('counts and narrows the recorded views', function (): void {
        $fake = Views::fake();
        $store = fn (array $dimensions, ?array $context = null, string $visitor = 'one') => $fake->store(new ViewRecord(
            $this->post->getKey(),
            $this->post->getMorphClass(),
            $visitor,
            null,
            Carbon::now(),
            context: $context,
            dimensions: $dimensions,
        ));

        $store(['source' => 'Google', 'device' => 'mobile'], ['plan' => 'pro']);
        $store(['source' => 'Google', 'device' => 'desktop'], visitor: 'two');
        $store(['source' => 'Bing', 'device' => 'mobile']);
        $store(['source' => 'X', 'device' => 'mobile'], visitor: 'three');
        $store([], ['plan' => 'free']);

        $counts = views($this->post)->countBy('source', limit: 2);

        expect($counts->all())->toBe(['Google' => 2, 'Bing' => 1])
            ->and($counts->other())->toBe(1)
            ->and($counts->none())->toBe(1)
            ->and($counts->total())->toBe(5)
            ->and(views($this->post)->unique()->countBy('source', limit: 1)->toArray())->toBe(['values' => ['Google' => 2], 'none' => 1, 'other' => 2, 'total' => 3])
            ->and(views($this->post)->countBy('plan')->all())->toBe(['free' => 1, 'pro' => 1])
            ->and(views($this->post)->whereDimension('device', 'mobile')->count())->toBe(3)
            ->and(views($this->post)->whereDimension('device', 'mobile')->whereDimension('source', ['Bing', 'X'])->count())->toBe(2)
            ->and(views($this->post)->whereDimension('plan', 'pro')->count())->toBe(1);
    });
});

it('reads a filter on a JSON path the way the driver extracts it', function (): void {
    viewWith($this->post, context: ['plan' => 'pro']);
    viewWith($this->post, context: ['plan' => 'free']);

    $query = View::query()->matching(new ViewsQuery(dimensions: [new DimensionFilter('plan', 'context->plan', ['pro'])]));

    expect($query->toRawSql())->not->toContain('context->plan')
        ->and($query->count())->toBe(1);
});
