<?php

declare(strict_types=1);

use Carbon\Carbon;
use CyrildeWit\EloquentViewable\Contracts\CreateView as CreateViewContract;
use CyrildeWit\EloquentViewable\Contracts\View as ViewContract;
use CyrildeWit\EloquentViewable\Jobs\StoreView;
use CyrildeWit\EloquentViewable\PendingView;
use Illuminate\Contracts\Queue\ShouldQueue;

beforeEach(function (): void {
    $this->pending = new PendingView(
        viewableId: 1,
        viewableType: 'App\Models\Post',
        visitor: 'visitor_one',
        collection: null,
        viewedAt: Carbon::now(),
    );
});

it('is queueable', function (): void {
    expect(new StoreView($this->pending))->toBeInstanceOf(ShouldQueue::class);
});

it('exposes the pending view so it is serialized with the job', function (): void {
    expect(new StoreView($this->pending)->pending)->toBe($this->pending);
});

it('hands the pending view to the create view action', function (): void {
    $action = Mockery::mock(CreateViewContract::class);
    $action->expects('handle')
        ->with($this->pending)
        ->andReturn(Mockery::mock(ViewContract::class));

    new StoreView($this->pending)->handle($action);
});
