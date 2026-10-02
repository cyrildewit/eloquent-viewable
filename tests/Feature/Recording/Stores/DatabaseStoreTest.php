<?php

declare(strict_types=1);

use Carbon\Carbon;
use CyrildeWit\EloquentViewable\Data\ViewRecord;
use CyrildeWit\EloquentViewable\Models\View;
use CyrildeWit\EloquentViewable\Recording\Contracts\ViewStore;
use CyrildeWit\EloquentViewable\Recording\Stores\DatabaseStore;
use CyrildeWit\EloquentViewable\Recording\Stores\StoreManager;
use CyrildeWit\EloquentViewable\Tests\Fixtures\Models\Post;
use CyrildeWit\EloquentViewable\Tests\Fixtures\Models\SoftDeletableView;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

beforeEach(function (): void {
    $this->post = Post::factory()->create();
    $this->store = $this->app->make(ViewStore::class);
});

it('is the default ViewStore', function (): void {
    expect($this->store)->toBeInstanceOf(DatabaseStore::class);
});

it('writes the record as a row', function (): void {
    $viewedAt = Carbon::parse('2021-01-01 12:30:00');

    $this->store->store(new ViewRecord(
        viewableId: $this->post->getKey(),
        viewableType: $this->post->getMorphClass(),
        visitor: 'visitor_one',
        collection: 'custom',
        viewedAt: $viewedAt,
    ));

    $view = View::sole();

    expect($view->viewable_id)->toBe($this->post->getKey())
        ->and($view->viewable_type)->toBe($this->post->getMorphClass())
        ->and($view->visitor)->toBe('visitor_one')
        ->and($view->collection)->toBe('custom')
        ->and(Carbon::parse($view->viewed_at)->equalTo($viewedAt))->toBeTrue();
});

it('writes a batch of records in one statement', function (): void {
    $other = Post::factory()->create();
    $viewedAt = Carbon::parse('2021-01-01 12:30:00');

    DB::enableQueryLog();

    $this->store->storeMany([
        new ViewRecord($this->post->getKey(), $this->post->getMorphClass(), 'visitor_one', null, $viewedAt),
        new ViewRecord($this->post->getKey(), $this->post->getMorphClass(), 'visitor_two', 'custom', $viewedAt),
        new ViewRecord($other->getKey(), $other->getMorphClass(), null, null, $viewedAt->copy()->addMinute()),
    ]);

    expect(DB::getQueryLog())->toHaveCount(1)
        ->and($this->post)->toHaveViewsCount(2)
        ->and($other)->toHaveViewsCount(1)
        ->and(View::where('collection', 'custom')->sole()->visitor)->toBe('visitor_two')
        ->and(Carbon::parse(View::where('visitor', null)->sole()->viewed_at)->equalTo($viewedAt->copy()->addMinute()))->toBeTrue();
});

it('accepts any iterable as a batch', function (): void {
    $records = (function (): Generator {
        yield new ViewRecord($this->post->getKey(), $this->post->getMorphClass(), 'visitor_one', null, Carbon::now());
        yield new ViewRecord($this->post->getKey(), $this->post->getMorphClass(), 'visitor_two', null, Carbon::now());
    })();

    $this->store->storeMany($records);

    expect($this->post)->toHaveViewsCount(2);
});

it('runs no query for an empty batch', function (): void {
    DB::enableQueryLog();

    $this->store->storeMany([]);

    expect(DB::getQueryLog())->toBeEmpty()
        ->and(View::count())->toBe(0);
});

it('does not fire view model events when writing', function (): void {
    $fired = [];
    View::creating(function () use (&$fired): void {
        $fired[] = 'creating';
    });
    View::created(function () use (&$fired): void {
        $fired[] = 'created';
    });

    $record = new ViewRecord($this->post->getKey(), $this->post->getMorphClass(), 'visitor_one', null, Carbon::now());

    $this->store->store($record);
    $this->store->storeMany([$record]);

    expect($fired)->toBeEmpty()
        ->and(View::count())->toBe(2);
});

it('forgets every view of the viewable in every collection', function (): void {
    View::factory()->for($this->post, 'viewable')->count(2)->create();
    View::factory()->for($this->post, 'viewable')->create(['collection' => 'custom']);

    $this->store->forget($this->post);

    expect(View::count())->toBe(0);
});

it('leaves the views of other viewables alone', function (): void {
    $other = Post::factory()->create();

    View::factory()->for($this->post, 'viewable')->count(2)->create();
    View::factory()->for($other, 'viewable')->count(3)->create();

    $this->store->forget($this->post);

    expect($other)->toHaveViewsCount(3)
        ->and(View::count())->toBe(3);
});

it('forgets through the configured view model', function (): void {
    Schema::table('views', function (Blueprint $table): void {
        $table->softDeletes();
    });

    $this->app['config']->set('eloquent-viewable.models.view.class', SoftDeletableView::class);
    $this->app->make(StoreManager::class)->forgetDrivers();

    View::factory()->for($this->post, 'viewable')->count(3)->create();

    $this->app->make(ViewStore::class)->forget($this->post);

    expect(SoftDeletableView::count())->toBe(0)
        ->and(SoftDeletableView::withTrashed()->count())->toBe(3);
});
