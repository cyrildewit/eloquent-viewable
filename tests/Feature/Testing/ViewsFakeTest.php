<?php

declare(strict_types=1);

use Carbon\Carbon;
use CyrildeWit\EloquentViewable\Data\ViewRecord;
use CyrildeWit\EloquentViewable\Exceptions\InvalidViewer;
use CyrildeWit\EloquentViewable\Facades\Views;
use CyrildeWit\EloquentViewable\Models\View;
use CyrildeWit\EloquentViewable\Querying\Contracts\ViewSource;
use CyrildeWit\EloquentViewable\Querying\Exceptions\UnsupportedBySource;
use CyrildeWit\EloquentViewable\Recording\Contracts\ViewStore;
use CyrildeWit\EloquentViewable\Recording\Stores\ArrayStore;
use CyrildeWit\EloquentViewable\Recording\Stores\StoreManager;
use CyrildeWit\EloquentViewable\Support\Granularity;
use CyrildeWit\EloquentViewable\Support\Period;
use CyrildeWit\EloquentViewable\Support\ViewsQuery;
use CyrildeWit\EloquentViewable\Testing\ViewsFake;
use CyrildeWit\EloquentViewable\Tests\Fixtures\Models\Apartment;
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
    $visitor->allows('isPrefetch')->andReturn(false);

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

    it('names a viewable without a key by its type alone', function (): void {
        expect(fn () => $this->fake->assertRecorded(new Post))
            ->toThrow(AssertionFailedError::class, 'No view of '.Post::class.' was recorded.');
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
    it('ranks what the visitors of a model also viewed', function (): void {
        config()->set('eloquent-viewable.querying.also_viewed.minimum_visitors', 1);
        $other = Post::factory()->create();
        $third = Post::factory()->create();

        Carbon::setTestNow('2026-09-01 10:00:00');
        views($this->post)->useVisitor(visitor('early'))->record();
        views($third)->useVisitor(visitor('early'))->record();

        Carbon::setTestNow('2026-09-05 10:00:00');
        views($this->post)->useVisitor(visitor('late'))->record();
        views($this->post)->useVisitor(visitor('late'))->record();
        views($other)->useVisitor(visitor('late'))->record();
        views($other)->useVisitor(visitor('early'))->record();
        views($other)->useVisitor(visitor('stranger'))->record();

        $ranked = fn (): array => views($this->post)->alsoViewed()->entries->map(fn ($entry): array => [$entry->viewable->getKey(), $entry->count])->all();

        expect($ranked())->toBe([[$other->getKey(), 2], [$third->getKey(), 1]])
            ->and(views($this->post)->alsoViewed(1)->viewables()->modelKeys())->toBe([$other->getKey()])
            ->and(views($this->post)->alsoViewed(among: Apartment::class)->isEmpty())->toBeTrue();

        config()->set('eloquent-viewable.querying.also_viewed.max_visitors', 1);

        expect($ranked())->toBe([[$other->getKey(), 1]]);

        config()->set('eloquent-viewable.querying.also_viewed.minimum_visitors', 2);
        config()->set('eloquent-viewable.querying.also_viewed.max_visitors');

        expect($ranked())->toBe([[$other->getKey(), 2]]);
    });

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

    it('compares with the previous period', function (): void {
        Carbon::setTestNow('2026-08-30 10:00:00');
        views($this->post)->record();

        Carbon::setTestNow('2026-09-05 10:00:00');
        views($this->post)->record();
        views($this->post)->record();

        expect(views($this->post)->period(Period::create('2026-09-01', '2026-09-08'))->compare()->toArray())
            ->toBe(['current' => 2, 'previous' => 1, 'delta' => 1, 'percent' => 100.0]);
    });

    it('counts a set of viewables', function (): void {
        $other = Post::factory()->create();
        $unviewed = Post::factory()->create();

        views($this->post)->useVisitor(visitor('one'))->record();
        views($this->post)->useVisitor(visitor('one'))->collection('custom')->record();
        views($other)->useVisitor(visitor('two'))->record();

        $views = fn (): CyrildeWit\EloquentViewable\Views => Views::getFacadeRoot()->forViewables([$unviewed, $this->post, $other]);

        expect($views()->counts()->all())->toBe([$unviewed->getKey() => 0, $this->post->getKey() => 2, $other->getKey() => 1])
            ->and($views()->unique()->counts()->all())->toBe([$unviewed->getKey() => 0, $this->post->getKey() => 1, $other->getKey() => 1])
            ->and($views()->collection('custom')->counts()->all())->toBe([$unviewed->getKey() => 0, $this->post->getKey() => 1, $other->getKey() => 0]);
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

    it('counts by collection, most viewed first, with the default collection as an empty string', function (): void {
        Carbon::setTestNow('2026-09-01 10:00:00');
        views($this->post)->useVisitor(visitor('one'))->collection('sidebar')->record();
        views($this->post)->useVisitor(visitor('one'))->collection('sidebar')->record();
        views($this->post)->useVisitor(visitor('two'))->collection('sidebar')->record();
        views($this->post)->useVisitor(visitor('one'))->record();
        views($this->post)->useVisitor(visitor('one'))->record();

        Carbon::setTestNow('2026-09-03 10:00:00');
        views($this->post)->useVisitor(visitor('three'))->collection('feed')->record();
        views(Post::factory()->create())->useVisitor(visitor('three'))->collection('feed')->record();

        expect(views($this->post)->countByCollection())->toBe(['sidebar' => 3, '' => 2, 'feed' => 1])
            ->and(views($this->post)->unique()->countByCollection())->toBe(['sidebar' => 2, '' => 1, 'feed' => 1])
            ->and(views($this->post)->period(Period::since('2026-09-02'))->countByCollection())->toBe(['feed' => 1])
            ->and(views($this->post)->collection('sidebar')->countByCollection())->toBe(['sidebar' => 3])
            ->and(views(Post::class)->countByCollection())->toBe(['sidebar' => 3, '' => 2, 'feed' => 2])
            ->and(views(Post::factory()->create())->countByCollection())->toBeEmpty();
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

    it('ranks the recorded views of every type, within the filters', function (): void {
        $other = Post::factory()->create();
        $apartment = Apartment::factory()->create();

        Carbon::setTestNow('2026-09-01 10:00:00');
        views($this->post)->useVisitor(visitor('one'))->record();
        views($this->post)->useVisitor(visitor('one'))->record();
        views($this->post)->useVisitor(visitor('two'))->collection('custom')->record();
        views($apartment)->useVisitor(visitor('one'))->record();
        views($apartment)->useVisitor(visitor('two'))->record();

        Carbon::setTestNow('2026-09-05 10:00:00');
        views($other)->useVisitor(visitor('one'))->record();

        $ids = fn (array $rows): array => array_map(fn (array $row): array => [$row['type'], $row['id'], $row['count']], $rows);

        expect($ids($this->fake->top(null, new ViewsQuery, 10)))->toBe([
            [$this->post->getMorphClass(), $this->post->getKey(), 3],
            [$apartment->getMorphClass(), $apartment->getKey(), 2],
            [$other->getMorphClass(), $other->getKey(), 1],
        ])
            ->and($ids($this->fake->top(new Post, new ViewsQuery, 10)))->toBe([
                [$this->post->getMorphClass(), $this->post->getKey(), 3],
                [$other->getMorphClass(), $other->getKey(), 1],
            ])
            ->and($ids($this->fake->top(null, new ViewsQuery(unique: true), 10)))->toBe([
                [$apartment->getMorphClass(), $apartment->getKey(), 2],
                [$this->post->getMorphClass(), $this->post->getKey(), 2],
                [$other->getMorphClass(), $other->getKey(), 1],
            ])
            ->and($ids($this->fake->top(null, new ViewsQuery(Period::since('2026-09-03')), 10)))->toBe([
                [$other->getMorphClass(), $other->getKey(), 1],
            ])
            ->and($ids($this->fake->top(null, new ViewsQuery(collection: 'custom'), 10)))->toBe([
                [$this->post->getMorphClass(), $this->post->getKey(), 1],
            ])
            ->and($ids($this->fake->top(null, new ViewsQuery, 1)))->toBe([
                [$this->post->getMorphClass(), $this->post->getKey(), 3],
            ]);
    });

    it('serves the ranking through Views::top() with the models from the database', function (): void {
        $apartment = Apartment::factory()->create();

        views($this->post)->record();
        views($apartment)->record();
        views($apartment)->record();

        expect(Views::top()->viewables()->map(fn ($model): array => [$model::class, $model->getKey()])->all())->toBe([
            [Apartment::class, $apartment->getKey()],
            [Post::class, $this->post->getKey()],
        ])
            ->and(views(Post::class)->top()->viewables()->modelKeys())->toBe([$this->post->getKey()]);
    });

    it('refuses the scopes', function (): void {
        $fake = ViewsFake::class;

        expect(fn () => Post::withViewsCount()->get())
            ->toThrow(UnsupportedBySource::class, "The view source [{$fake}] cannot be queried in SQL")
            ->and(fn () => Post::whereViewedByVisitor('visitor_one')->get())
            ->toThrow(UnsupportedBySource::class);
    });
});

it('ships the array store as a driver', function (): void {
    expect($this->app->make(StoreManager::class)->driver('array'))->toBeInstanceOf(ArrayStore::class);
});
