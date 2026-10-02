<?php

declare(strict_types=1);

use Carbon\Carbon;
use CyrildeWit\EloquentViewable\Data\ViewRecord;
use CyrildeWit\EloquentViewable\Recording\Contracts\RecordingGuard;
use CyrildeWit\EloquentViewable\Recording\Contracts\RecordsViews;
use CyrildeWit\EloquentViewable\Recording\Contracts\RemembersRecordedViews;
use CyrildeWit\EloquentViewable\Recording\Data\RecordResult;
use CyrildeWit\EloquentViewable\Recording\Data\ViewAttempt;
use CyrildeWit\EloquentViewable\Recording\Events\ViewSkipped;
use CyrildeWit\EloquentViewable\Recording\Exceptions\RecordingFailed;
use CyrildeWit\EloquentViewable\Recording\Jobs\RecordViewJob;
use CyrildeWit\EloquentViewable\Recording\Recorder;
use CyrildeWit\EloquentViewable\Support\Config;
use CyrildeWit\EloquentViewable\Tests\Fixtures\Models\Post;
use CyrildeWit\EloquentViewable\Visitors\Contracts\Visitor;
use Illuminate\Config\Repository;
use Illuminate\Contracts\Bus\Dispatcher as BusDispatcher;
use Illuminate\Contracts\Events\Dispatcher as EventDispatcher;

/**
 * @param  list<RecordingGuard>  $guards
 * @param  array<string, mixed>  $queue
 */
function recorder(array $guards, BusDispatcher $bus, RecordsViews $action, array $queue = [], ?EventDispatcher $events = null): Recorder
{
    return new Recorder(
        $guards,
        new Config(new Repository(['eloquent-viewable' => ['recording' => ['queue' => $queue]]])),
        $bus,
        $events ?? silentEvents(),
        $action,
    );
}

function silentEvents(): EventDispatcher
{
    $events = Mockery::mock(EventDispatcher::class);
    $events->shouldNotReceive('dispatch');

    return $events;
}

function attempt(?bool $queue = null, ?Carbon $cooldown = null): ViewAttempt
{
    $visitor = Mockery::mock(Visitor::class);
    $visitor->allows('id')->andReturn('visitor_one');

    return new ViewAttempt(new Post(['id' => 7]), $visitor, 'custom', $cooldown, $queue);
}

function guardThat(bool $allows, ?ViewAttempt $expected = null): RecordingGuard
{
    $guard = Mockery::mock(RecordingGuard::class);
    $expectation = $guard->expects('allows')->andReturn($allows);

    if ($expected instanceof ViewAttempt) {
        $expectation->with($expected);
    }

    return $guard;
}

function neverRuns(): RecordingGuard
{
    $guard = Mockery::mock(RecordingGuard::class);
    $guard->shouldNotReceive('allows');

    return $guard;
}

function rememberingGuard(bool $remembered, ?ViewAttempt $expected = null): RecordingGuard&RemembersRecordedViews
{
    $guard = Mockery::mock(RecordingGuard::class.', '.RemembersRecordedViews::class);
    $guard->shouldReceive('allows')->andReturn(true);

    if ($remembered) {
        $guard->expects('remember')->with($expected);
    } else {
        $guard->shouldNotReceive('remember');
    }

    return $guard;
}

function recordFor(ViewAttempt $attempt): Closure
{
    return fn (ViewRecord $record): bool => $record->viewableId === 7
        && $record->viewableType === Post::class
        && $record->visitor === 'visitor_one'
        && $record->collection === 'custom'
        && $record->viewedAt->equalTo(Carbon::now());
}

beforeEach(function (): void {
    Carbon::setTestNow('2026-10-02 12:00:00');
});

it('refuses a viewable without a key', function (): void {
    $visitor = Mockery::mock(Visitor::class);

    expect(fn (): RecordResult => recorder([neverRuns()], Mockery::mock(BusDispatcher::class), Mockery::mock(RecordsViews::class))
        ->record(new ViewAttempt(new Post, $visitor)))
        ->toThrow(RecordingFailed::class);
});

it('hands the record to the action when nothing refuses', function (): void {
    $attempt = attempt();

    $action = Mockery::mock(RecordsViews::class);
    $action->expects('handle')->with(Mockery::on(recordFor($attempt)));

    $result = recorder([guardThat(true, $attempt), guardThat(true, $attempt)], Mockery::mock(BusDispatcher::class), $action)->record($attempt);

    expect($result->recorded)->toBeTrue()
        ->and($result->queued)->toBeFalse()
        ->and($result->skippedBy)->toBeNull();
});

it('records without guards', function (): void {
    $attempt = attempt();

    $action = Mockery::mock(RecordsViews::class);
    $action->expects('handle')->with(Mockery::on(recordFor($attempt)));

    expect(recorder([], Mockery::mock(BusDispatcher::class), $action)->record($attempt)->recorded)->toBeTrue();
});

it('stops at the first guard that refuses and says which one', function (): void {
    $attempt = attempt();
    $refusing = guardThat(false);

    $action = Mockery::mock(RecordsViews::class);
    $action->shouldNotReceive('handle');

    $events = Mockery::mock(EventDispatcher::class);
    $events->expects('dispatch')->with(Mockery::on(fn (ViewSkipped $event): bool => $event->attempt === $attempt && $event->guard === $refusing));

    $result = recorder([guardThat(true), $refusing, neverRuns()], Mockery::mock(BusDispatcher::class), $action, events: $events)->record($attempt);

    expect($result->recorded)->toBeFalse()
        ->and($result->queued)->toBeFalse()
        ->and($result->skippedBy)->toBe($refusing);
});

it('queues the record when the attempt asks for it', function (): void {
    $attempt = attempt(queue: true);

    $bus = Mockery::mock(BusDispatcher::class);
    $bus->expects('dispatch')->with(Mockery::on(fn (RecordViewJob $job): bool => recordFor($attempt)($job->record)
        && $job->connection === 'sqs'
        && $job->queue === 'views'));

    $action = Mockery::mock(RecordsViews::class);
    $action->shouldNotReceive('handle');

    $result = recorder([], $bus, $action, ['enabled' => false, 'connection' => 'sqs', 'queue' => 'views'])->record($attempt);

    expect($result->recorded)->toBeTrue()
        ->and($result->queued)->toBeTrue()
        ->and($result->skippedBy)->toBeNull();
});

it('queues the record when the config asks for it and the attempt does not say', function (): void {
    $attempt = attempt();

    $bus = Mockery::mock(BusDispatcher::class);
    $bus->expects('dispatch')->with(Mockery::on(fn (RecordViewJob $job): bool => $job->connection === null && $job->queue === null));

    expect(recorder([], $bus, Mockery::mock(RecordsViews::class), ['enabled' => true])->record($attempt)->queued)->toBeTrue();
});

it('records synchronously when the attempt overrides the config', function (): void {
    $attempt = attempt(queue: false);

    $action = Mockery::mock(RecordsViews::class);
    $action->expects('handle')->with(Mockery::on(recordFor($attempt)));

    $bus = Mockery::mock(BusDispatcher::class);
    $bus->shouldNotReceive('dispatch');

    expect(recorder([], $bus, $action, ['enabled' => true])->record($attempt)->queued)->toBeFalse();
});

it('remembers the view once every guard has allowed it and it is handed on', function (bool $queue): void {
    $attempt = attempt(queue: $queue);

    $action = Mockery::mock(RecordsViews::class);
    $action->allows('handle');

    $bus = Mockery::mock(BusDispatcher::class);
    $bus->allows('dispatch');

    expect(recorder([rememberingGuard(true, $attempt), guardThat(true)], $bus, $action)->record($attempt)->recorded)->toBeTrue();
})->with([
    'stored' => [false],
    'queued' => [true],
]);

it('does not remember a view a later guard refuses', function (): void {
    $attempt = attempt();

    $events = Mockery::mock(EventDispatcher::class);
    $events->allows('dispatch');

    expect(recorder([rememberingGuard(false), guardThat(false)], Mockery::mock(BusDispatcher::class), Mockery::mock(RecordsViews::class), events: $events)->record($attempt)->recorded)->toBeFalse();
});

it('does not remember a view the action fails to store', function (): void {
    $attempt = attempt();

    $action = Mockery::mock(RecordsViews::class);
    $action->expects('handle')->andThrow(new RuntimeException('The store is down.'));

    expect(fn (): RecordResult => recorder([rememberingGuard(false)], Mockery::mock(BusDispatcher::class), $action)->record($attempt))
        ->toThrow(RuntimeException::class, 'The store is down.');
});
