<?php

declare(strict_types=1);

use Carbon\Carbon;
use CyrildeWit\EloquentViewable\Data\ViewRecord;
use CyrildeWit\EloquentViewable\Exceptions\InvalidViewable;
use CyrildeWit\EloquentViewable\Exceptions\InvalidViewer;
use CyrildeWit\EloquentViewable\Facades\Views;
use CyrildeWit\EloquentViewable\Models\View;
use CyrildeWit\EloquentViewable\Presence\Contracts\PresenceStore;
use CyrildeWit\EloquentViewable\Querying\Contracts\ViewSource;
use CyrildeWit\EloquentViewable\Querying\Exceptions\UnsupportedBySource;
use CyrildeWit\EloquentViewable\Querying\Recommendations\Recipient;
use CyrildeWit\EloquentViewable\Querying\Recommendations\RecommendationRequest;
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
use Illuminate\Database\Eloquent\Model;
use PHPUnit\Framework\AssertionFailedError;

beforeEach(function (): void {
    $this->post = Post::factory()->create();
    $this->fake = Views::fake();
});

function visitor(string $id): Visitor
{
    $visitor = Mockery::mock(Visitor::class);
    $visitor->allows('id')->andReturn($id);
    $visitor->allows('viewer')->andReturn(null);
    $visitor->allows('ip')->andReturn('127.0.0.1');
    $visitor->allows('userAgent')->andReturn('Mozilla/5.0');
    $visitor->allows('hasDoNotTrackHeader')->andReturn(false);
    $visitor->allows('isPrefetch')->andReturn(false);
    $visitor->allows('isHeadRequest')->andReturn(false);

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

    it('counts how many days each visitor viewed on', function (): void {
        $this->fake->storeMany([
            new ViewRecord($this->post->getKey(), $this->post->getMorphClass(), 'one', null, Carbon::parse('2026-09-01 10:00:00')),
            new ViewRecord($this->post->getKey(), $this->post->getMorphClass(), 'one', null, Carbon::parse('2026-09-01 18:00:00')),
            new ViewRecord($this->post->getKey(), $this->post->getMorphClass(), 'one', null, Carbon::parse('2026-09-03 23:30:00')),
            new ViewRecord($this->post->getKey(), $this->post->getMorphClass(), 'two', null, Carbon::parse('2026-09-02 10:00:00')),
            new ViewRecord($this->post->getKey(), $this->post->getMorphClass(), 'three', null, Carbon::parse('2026-09-03 10:00:00')),
            new ViewRecord($this->post->getKey(), $this->post->getMorphClass(), 'three', null, Carbon::parse('2026-09-04 00:30:00')),
            new ViewRecord($this->post->getKey(), $this->post->getMorphClass(), null, null, Carbon::parse('2026-09-02 10:00:00')),
            new ViewRecord($this->post->getKey(), $this->post->getMorphClass(), 'a:anonymised', null, Carbon::parse('2026-09-02 10:00:00')),
        ]);

        expect(views($this->post)->countByFrequency()->toArray())->toBe([1 => 1, 2 => 2, '3+' => 0])
            ->and(views($this->post)->returning()->count())->toBe(2)
            ->and(views($this->post)->period(Period::create('2026-09-01', '2026-09-05'))->timezone('Australia/Sydney')->countByFrequency()->toArray())
            ->toBe([1 => 1, 2 => 1, '3+' => 1]);
    });

    it('reads what recommendations are made from, as the database does', function (): void {
        config()->set('eloquent-viewable.querying.also_viewed.minimum_visitors', 1);
        $user = User::factory()->create();
        $earlier = Post::factory()->create();
        $next = Post::factory()->create();
        $apartment = Apartment::factory()->create();

        $record = fn (Model $viewable, ?string $visitor, string $viewedAt, ?User $viewer = null, ?string $collection = null): null => $this->fake->store(new ViewRecord(
            $viewable->getKey(),
            $viewable->getMorphClass(),
            $visitor,
            $collection,
            Carbon::parse($viewedAt),
            $viewer?->getMorphClass(),
            $viewer?->getKey(),
        ));

        $record($this->post, 'laptop', '2026-01-10', $user);
        $record($earlier, 'phone', '2026-01-03', $user);
        $record($next, 'phone', '2025-01-01', $user);
        $record($this->post, 'one', '2026-01-09');
        $record($this->post, 'two', '2026-01-08');
        $record($this->post, null, '2026-01-08');
        $record($next, 'one', '2026-01-09');
        $record($next, 'two', '2026-01-09');
        $record($next, 'laptop', '2026-01-09');
        $record($apartment, 'one', '2026-01-09');
        $record($earlier, 'one', '2026-01-09', collection: 'sidebar');

        $pairs = fn (?Model $among = null, ?int $maxVisitors = null, bool $includeSeen = false, int $seeds = 20, int $minimum = 1, ?Recipient $recipient = null): array => $this->fake->recommendationPairs(
            new RecommendationRequest($recipient ?? Recipient::viewer($user), $among, $seeds, $minimum, $maxVisitors, $includeSeen),
            new ViewsQuery(Period::since('2026-01-01')),
        );

        $all = $pairs(includeSeen: true);

        expect($all['seeds'])->toEqual([
            ['type' => Post::class, 'id' => $this->post->getKey(), 'viewed_at' => '2026-01-10 00:00:00'],
            ['type' => Post::class, 'id' => $earlier->getKey(), 'viewed_at' => '2026-01-03 00:00:00'],
        ])
            ->and($all['pairs'])->toEqual([
                ['seed_type' => Post::class, 'seed_id' => $this->post->getKey(), 'type' => Apartment::class, 'id' => $apartment->getKey(), 'visitors' => 1],
                ['seed_type' => Post::class, 'seed_id' => $this->post->getKey(), 'type' => Post::class, 'id' => $next->getKey(), 'visitors' => 2],
                ['seed_type' => Post::class, 'seed_id' => $earlier->getKey(), 'type' => Apartment::class, 'id' => $apartment->getKey(), 'visitors' => 1],
                ['seed_type' => Post::class, 'seed_id' => $earlier->getKey(), 'type' => Post::class, 'id' => $next->getKey(), 'visitors' => 1],
            ])
            ->and($all['audiences'])->toEqualCanonicalizing([
                ['type' => Post::class, 'id' => $this->post->getKey(), 'visitors' => 3],
                ['type' => Post::class, 'id' => $earlier->getKey(), 'visitors' => 2],
                ['type' => Post::class, 'id' => $next->getKey(), 'visitors' => 3],
                ['type' => Apartment::class, 'id' => $apartment->getKey(), 'visitors' => 1],
            ])
            ->and(array_column($pairs()['pairs'], 'id'))->toBe([$apartment->getKey(), $apartment->getKey()])
            ->and(array_column($pairs(among: new Post, includeSeen: true)['pairs'], 'id'))->toBe([$next->getKey(), $next->getKey()])
            ->and(array_column($pairs(maxVisitors: 1, includeSeen: true)['pairs'], 'visitors'))->toBe([1, 1, 1, 1])
            ->and(array_column($pairs(seeds: 1)['seeds'], 'id'))->toBe([$this->post->getKey()])
            ->and($pairs(minimum: 3))->toBe(['seeds' => $pairs()['seeds'], 'pairs' => [], 'audiences' => []])
            ->and(count($pairs(recipient: Recipient::visitor('one'))['seeds']))->toBe(4)
            ->and(array_column($pairs(recipient: Recipient::visitor('two'))['pairs'], 'id'))->toBe([$apartment->getKey(), $earlier->getKey(), $apartment->getKey(), $earlier->getKey()]);
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

describe('presence', function (): void {
    beforeEach(function (): void {
        config()->set('eloquent-viewable.presence.enabled', true);
    });

    it('stands in for the presence store', function (): void {
        expect($this->app->make(PresenceStore::class))->toBe($this->fake);
    });

    it('puts visitors on a viewable for a page that shows a live count', function (): void {
        $this->fake->present($this->post, 3)->present($this->post, 1, 'amp');

        expect(views($this->post)->activeVisitors())->toBe(3)
            ->and(views($this->post)->collection('amp')->activeVisitors())->toBe(1)
            ->and(Views::live()->top()->viewables()->modelKeys())->toBe([$this->post->getKey()])
            ->and(Views::forViewables([$this->post])->live()->counts()->all())->toBe([$this->post->getKey() => 3]);
    });

    it('needs a saved model to put visitors on', function (): void {
        $this->fake->present(new Post);
    })->throws(InvalidViewable::class, 'Visitors are put on a saved model, an unsaved [CyrildeWit\EloquentViewable\Tests\Fixtures\Models\Post] was given.');

    it('asserts a visitor was kept active', function (): void {
        $other = Post::factory()->create();

        views($this->post)->record();

        $this->fake->assertPresent($this->post);
        $this->fake->assertNotPresent($other);

        expect(fn () => $this->fake->assertPresent($other))
            ->toThrow(AssertionFailedError::class, "No visitor was kept active on {$other->getMorphClass()} {$other->getKey()}.")
            ->and(fn () => $this->fake->assertNotPresent($this->post))
            ->toThrow(AssertionFailedError::class, "A visitor was kept active on {$this->post->getMorphClass()} {$this->post->getKey()}.");
    });

    it('asserts a visitor left', function (): void {
        views($this->post)->heartbeat();

        expect(fn () => $this->fake->assertLeft($this->post))
            ->toThrow(AssertionFailedError::class, "No visitor left {$this->post->getMorphClass()} {$this->post->getKey()}.");

        views($this->post)->leave();

        $this->fake->assertLeft($this->post);

        expect(views($this->post)->activeVisitors())->toBe(0);
    });

    it('lists the viewers it kept', function (): void {
        config()->set('eloquent-viewable.presence.viewers', true);

        $user = User::factory()->create();

        views($this->post)->viewedBy($user)->record();

        expect(views($this->post)->live()->viewers()->modelKeys())->toBe([$user->getKey()]);
    });
});
