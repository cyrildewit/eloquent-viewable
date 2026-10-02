<?php

declare(strict_types=1);

use Carbon\Carbon;
use CyrildeWit\EloquentViewable\Data\ViewRecord;
use CyrildeWit\EloquentViewable\Models\View;
use CyrildeWit\EloquentViewable\Recording\Actions\RecordView;
use CyrildeWit\EloquentViewable\Recording\Contracts\RecordsViews as RecordsViewsContract;
use CyrildeWit\EloquentViewable\Recording\Events\ViewRecorded;
use CyrildeWit\EloquentViewable\Tests\Fixtures\Models\Post;
use Illuminate\Support\Facades\Event;

beforeEach(function (): void {
    $this->post = Post::factory()->create();
});

it('is bound to the RecordsViews contract', function (): void {
    expect($this->app->make(RecordsViewsContract::class))->toBeInstanceOf(RecordView::class);
});

it('stores a record', function (): void {
    $record = new ViewRecord(
        viewableId: $this->post->getKey(),
        viewableType: $this->post->getMorphClass(),
        visitor: 'visitor_one',
        collection: 'custom',
        viewedAt: Carbon::now(),
    );

    $this->app->make(RecordsViewsContract::class)->handle($record);

    $view = View::sole();

    expect($view->viewable_id)->toBe($this->post->getKey())
        ->and($view->visitor)->toBe('visitor_one')
        ->and($view->collection)->toBe('custom');
});

it('dispatches a ViewRecorded event carrying the record', function (): void {
    Event::fake();

    $record = new ViewRecord(
        viewableId: $this->post->getKey(),
        viewableType: $this->post->getMorphClass(),
        visitor: 'visitor_one',
        collection: null,
        viewedAt: Carbon::now(),
    );

    $this->app->make(RecordsViewsContract::class)->handle($record);

    Event::assertDispatched(ViewRecorded::class, fn (ViewRecorded $event): bool => $event->record === $record);
});
