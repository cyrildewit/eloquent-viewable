<?php

declare(strict_types=1);

use Carbon\Carbon;
use CyrildeWit\EloquentViewable\Data\ViewRecord;
use CyrildeWit\EloquentViewable\Recording\Contracts\RecordsViews as RecordsViewsContract;
use CyrildeWit\EloquentViewable\Recording\Jobs\RecordViewJob;
use Illuminate\Contracts\Queue\ShouldQueue;

beforeEach(function (): void {
    $this->record = new ViewRecord(
        viewableId: 1,
        viewableType: 'App\Models\Post',
        visitor: 'visitor_one',
        collection: null,
        viewedAt: Carbon::now(),
    );
});

it('is queueable', function (): void {
    expect(new RecordViewJob($this->record))->toBeInstanceOf(ShouldQueue::class);
});

it('exposes the record so it is serialized with the job', function (): void {
    expect(new RecordViewJob($this->record)->record)->toBe($this->record);
});

it('hands the record to the record view action', function (): void {
    $action = Mockery::mock(RecordsViewsContract::class);
    $action->expects('handle')->with($this->record);

    new RecordViewJob($this->record)->handle($action);
});
