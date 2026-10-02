<?php

declare(strict_types=1);

use Carbon\Carbon;
use CyrildeWit\EloquentViewable\Data\ViewRecord;
use CyrildeWit\EloquentViewable\Exceptions\InvalidViewer;
use CyrildeWit\EloquentViewable\Facades\Views;
use CyrildeWit\EloquentViewable\Models\View;
use CyrildeWit\EloquentViewable\Querying\Contracts\ViewSource;
use CyrildeWit\EloquentViewable\Recording\Contracts\ViewStore;
use CyrildeWit\EloquentViewable\Recording\Stores\ArrayStore;
use CyrildeWit\EloquentViewable\Recording\Stores\StoreManager;
use CyrildeWit\EloquentViewable\Support\Granularity;
use CyrildeWit\EloquentViewable\Support\Period;
use CyrildeWit\EloquentViewable\Testing\Exceptions\UnsupportedInFake;
use CyrildeWit\EloquentViewable\Testing\ViewsFake;
use CyrildeWit\EloquentViewable\Tests\Fixtures\Models\Post;
use CyrildeWit\EloquentViewable\Tests\Fixtures\Models\User;
use CyrildeWit\EloquentViewable\Visitors\Contracts\Visitor;
use PHPUnit\Framework\AssertionFailedError;

beforeEach(function (): void {
    $this->post = Post::factory()->create();
    $this->fake = Views::fake();
});

function visitor(string $id): Visitor
{
    $visitor = Mockery::mock(Visitor::class);
    $visitor->allows('id')->andReturn($id);
    $visitor->allows('ip')->andReturn('127.0.0.1');
    $visitor->allows('userAgent')->andReturn(null);
    $visitor->allows('hasDoNotTrackHeader')->andReturn(false);

    return $visitor;
}

it('stands in for the store and the source', function (): void {
    expect($this->fake)->toBeInstanceOf(ViewsFake::class)
        ->and($this->app->make(ViewStore::class))->toBe($this->fake)
        ->and($this->app->make(ViewSource::class))->toBe($this->fake);
});

it('keeps recorded views out of the database', function (): void {
    expect(views($this->post)->record())->toBeTrue()
        ->and(View::count())->toBe(0);

    $this->fake->assertRecorded($this->post);
});

it('keeps a batch of records in memory', function (): void {
    $record = new ViewRecord($this->post->getKey(), $this->post->getMorphClass(), 'visitor_one', null, Carbon::now());

    $this->fake->storeMany([$record, $record]);

    expect(View::count())->toBe(0);

    $this->fake->assertRecorded($this->post, 2);
});

describe('assertions', function (): void {
    it('fails when nothing was recorded for the viewable', function (): void {
        views(Post::factory()->create())->record();

        expect(fn () => $this->fake->assertRecorded($this->post))
            ->toThrow(AssertionFailedError::class, 'No view of '.Post::class." {$this->post->getKey()} was recorded.");
    });

    it('asserts an exact number of views', function (): void {
        views($this->post)->record();
        views($this->post)->record();

        $this->fake->assertRecorded($this->post, 2);

        expect(fn () => $this->fake->assertRecorded($this->post, 3))
            ->toThrow(AssertionFailedError::class, 'Expected 3 views of '.Post::class." {$this->post->getKey()}, 2 recorded.");
    });

    it('asserts with a filter on the record', function (): void {
        views($this->post)->collection('custom')->record();

        $this->fake->assertRecorded($this->post, fn (ViewRecord $record): bool => $record->collection === 'custom');

        expect(fn () => $this->fake->assertRecorded($this->post, fn (ViewRecord $record): bool => $record->collection === 'other'))
            ->toThrow(AssertionFailedError::class, 'that matches the filter.');
    });

    it('asserts a viewable was not recorded', function (): void {
        $this->fake->assertNotRecorded($this->post);

        views($this->post)->collection('custom')->record();

        $this->fake->assertNotRecorded($this->post, fn (ViewRecord $record): bool => $record->collection === 'other');

        expect(fn () => $this->fake->assertNotRecorded($this->post))
            ->toThrow(AssertionFailedError::class, 'A view of '.Post::class." {$this->post->getKey()} was recorded.");
    });

    it('asserts nothing was recorded at all', function (): void {
        $this->fake->assertNothingRecorded();

        views($this->post)->record();

        expect(fn () => $this->fake->assertNothingRecorded())
            ->toThrow(AssertionFailedError::class, 'Views were recorded unexpectedly, 1 in total.');
    });

    it('asserts the views of a viewable were forgotten', function (): void {
        views($this->post)->record();

        expect(fn () => $this->fake->assertForgotten($this->post))
            ->toThrow(AssertionFailedError::class, 'were not forgotten.');

        views($this->post)->destroy();

        $this->fake->assertForgotten($this->post);
        $this->fake->assertNotRecorded($this->post);
    });

    it('sees a force delete forget the views', function (): void {
        views($this->post)->record();

        $this->post->delete();

        $this->fake->assertForgotten($this->post);
    });

    it('exposes the recorded views of a type', function (): void {
        views($this->post)->record();
        views(Post::factory()->create())->record();

        expect($this->fake->recorded(new Post))->toHaveCount(2)
            ->and($this->fake->recorded($this->post))->toHaveCount(1);
    });
});

describe('counting', function (): void {
    it('counts what was recorded', function (): void {
        views($this->post)->record();
        views($this->post)->record();
        views(Post::factory()->create())->record();

        expect(views($this->post)->count())->toBe(2)
            ->and(views(Post::class)->count())->toBe(3);
    });

    it('counts unique visitors', function (): void {
        views($this->post)->useVisitor(visitor('one'))->record();
        views($this->post)->useVisitor(visitor('one'))->record();
        views($this->post)->useVisitor(visitor('two'))->record();

        expect(views($this->post)->unique()->count())->toBe(2);
    });

    it('applies the period and collection', function (): void {
        Carbon::setTestNow('2026-09-01 10:00:00');
        views($this->post)->collection('custom')->record();

        Carbon::setTestNow('2026-09-05 10:00:00');
        views($this->post)->collection('custom')->record();
        views($this->post)->record();

        expect(views($this->post)->period(Period::since('2026-09-03'))->count())->toBe(2)
            ->and(views($this->post)->period(Period::upto('2026-09-03'))->count())->toBe(1)
            ->and(views($this->post)->collection('custom')->count())->toBe(2)
            ->and(views($this->post)->period(Period::create('2026-09-01', '2026-09-03'))->collection('custom')->count())->toBe(1);
    });

    it('counts by interval', function (): void {
        Carbon::setTestNow('2026-09-01 10:00:00');
        views($this->post)->useVisitor(visitor('one'))->record();
        views($this->post)->useVisitor(visitor('one'))->record();

        Carbon::setTestNow('2026-09-03 10:00:00');
        views($this->post)->useVisitor(visitor('two'))->record();

        $series = views($this->post)->period(Period::create('2026-09-01', '2026-09-04'))->countByInterval(Granularity::Day);
        $unique = views($this->post)->period(Period::create('2026-09-01', '2026-09-04'))->unique()->countByInterval(Granularity::Day);

        expect($series->intervals->pluck('count')->all())->toBe([2, 0, 1])
            ->and($unique->intervals->pluck('count')->all())->toBe([1, 0, 1]);
    });

    it('counts by interval on the clock of the timezone', function (): void {
        // 15:00 UTC on the 1st is already the 2nd in Sydney.
        Carbon::setTestNow('2026-09-01 15:00:00');
        views($this->post)->useVisitor(visitor('one'))->record();

        $period = Period::create('2026-09-01', '2026-09-03');

        expect(views($this->post)->period($period)->countByInterval(Granularity::Day)->intervals->pluck('count')->all())->toBe([1, 0])
            ->and(views($this->post)->period($period)->timezone('Australia/Sydney')->countByInterval(Granularity::Day)->intervals->pluck('count')->all())->toBe([0, 1, 0]);
    });

    it('counts the views of one viewer', function (): void {
        $user = User::factory()->create();
        $other = User::factory()->create();

        views($this->post)->viewedBy($user)->record();
        views($this->post)->viewedBy($user)->record();
        views($this->post)->viewedBy($other)->record();
        views($this->post)->record();

        expect(views($this->post)->viewedBy($user)->count())->toBe(2)
            ->and(views($this->post)->viewedBy($other)->count())->toBe(1)
            ->and(views($this->post)->viewedBy(User::factory()->create())->count())->toBe(0)
            ->and(views($this->post)->count())->toBe(4);

        $this->fake->assertRecorded($this->post, fn (ViewRecord $record): bool => $record->viewerType === $user->getMorphClass() && $record->viewerId === $user->getKey());
    });

    it('refuses a viewer without a key, as the database does', function (): void {
        views($this->post)->record();

        expect(fn (): int => views($this->post)->viewedBy(new User)->count())
            ->toThrow(InvalidViewer::class, 'The key of the viewer ['.User::class.'] must be an integer or a string, null given.');
    });

    it('counts by interval for one viewer', function (): void {
        $user = User::factory()->create();

        Carbon::setTestNow('2026-09-01 10:00:00');
        views($this->post)->viewedBy($user)->record();
        views($this->post)->record();

        $series = views($this->post)->period(Period::create('2026-09-01', '2026-09-03'))->viewedBy($user)->countByInterval(Granularity::Day);

        expect($series->intervals->pluck('count')->all())->toBe([1, 0]);
    });

    it('keeps the context on the record', function (): void {
        views($this->post)->context(['source' => 'newsletter'])->record();

        $this->fake->assertRecorded($this->post, fn (ViewRecord $record): bool => $record->context === ['source' => 'newsletter']);
    });

    it('refuses the scopes', function (): void {
        expect(fn () => Post::withViewsCount()->get())
            ->toThrow(UnsupportedInFake::class, 'withViewsCount() and orderByViews() cannot read from it');
    });
});

it('ships the array store as a driver', function (): void {
    expect($this->app->make(StoreManager::class)->driver('array'))->toBeInstanceOf(ArrayStore::class);
});
