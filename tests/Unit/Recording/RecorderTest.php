<?php

declare(strict_types=1);

use Carbon\Carbon;
use CyrildeWit\EloquentViewable\Data\ViewRecord;
use CyrildeWit\EloquentViewable\Exceptions\InvalidViewer;
use CyrildeWit\EloquentViewable\Recording\Contracts\RecordingGuard;
use CyrildeWit\EloquentViewable\Recording\Contracts\RecordsViews;
use CyrildeWit\EloquentViewable\Recording\Contracts\RemembersRecordedViews;
use CyrildeWit\EloquentViewable\Recording\Data\RecordResult;
use CyrildeWit\EloquentViewable\Recording\Data\ViewAttempt;
use CyrildeWit\EloquentViewable\Recording\Events\ViewAttempted;
use CyrildeWit\EloquentViewable\Recording\Events\ViewSkipped;
use CyrildeWit\EloquentViewable\Recording\Exceptions\RecordingFailed;
use CyrildeWit\EloquentViewable\Recording\Jobs\RecordViewJob;
use CyrildeWit\EloquentViewable\Recording\Recorder;
use CyrildeWit\EloquentViewable\Support\Config;
use CyrildeWit\EloquentViewable\Tests\Fixtures\Models\Apartment;
use CyrildeWit\EloquentViewable\Tests\Fixtures\Models\Post;
use CyrildeWit\EloquentViewable\Visitors\Contracts\Visitor;
use CyrildeWit\EloquentViewable\Visitors\Fingerprint;
use CyrildeWit\EloquentViewable\Visitors\VisitorIdentity;
use Illuminate\Config\Repository;
use Illuminate\Contracts\Bus\Dispatcher as BusDispatcher;
use Illuminate\Contracts\Cache\Factory as CacheFactory;
use Illuminate\Contracts\Events\Dispatcher as EventDispatcher;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Encryption\Encrypter;

const RECORDER_KEY = 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa';

/**
 * @param  list<RecordingGuard>  $guards
 * @param  array<string, mixed>  $queue
 */
function recorder(array $guards, BusDispatcher $bus, RecordsViews $action, array $queue = [], ?EventDispatcher $events = null, bool $viewerEnabled = false, string $identity = 'cookie'): Recorder
{
    $config = new Config(new Repository([
        'eloquent-viewable' => [
            'recording' => ['queue' => $queue, 'viewer' => ['enabled' => $viewerEnabled]],
            'visitor' => ['identity' => $identity],
        ],
    ]));

    return new Recorder(
        $guards,
        $config,
        $bus,
        $events ?? silentEvents(),
        $action,
        new VisitorIdentity($config, new Encrypter(RECORDER_KEY, 'AES-256-CBC'), new Fingerprint($config, Mockery::mock(CacheFactory::class))),
    );
}

function identifyingVisitor(?Model $viewer): Visitor
{
    $visitor = Mockery::mock(Visitor::class);
    $visitor->allows('id')->andReturn('visitor_one');
    $visitor->allows('viewer')->andReturn($viewer);

    return $visitor;
}

function recordingAction(Closure $matches): RecordsViews
{
    $action = Mockery::mock(RecordsViews::class);
    $action->expects('handle')->with(Mockery::on($matches));

    return $action;
}

function silentEvents(): EventDispatcher
{
    $events = Mockery::mock(EventDispatcher::class);
    $events->allows('hasListeners')->with(ViewAttempted::class)->andReturn(false);
    $events->shouldNotReceive('dispatch');

    return $events;
}

function listeningEvents(Closure $matches): EventDispatcher
{
    $events = Mockery::mock(EventDispatcher::class);
    $events->allows('hasListeners')->with(ViewAttempted::class)->andReturn(true);
    $events->allows('dispatch')->with(Mockery::type(ViewSkipped::class));
    $events->expects('dispatch')->with(Mockery::on(fn (object $event): bool => $event instanceof ViewAttempted && $matches($event)));

    return $events;
}

function attempt(?bool $queue = null, ?Carbon $cooldown = null): ViewAttempt
{
    $visitor = Mockery::mock(Visitor::class);
    $visitor->allows('id')->andReturn('visitor_one');
    $visitor->allows('viewer')->andReturn(null);

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

    if (! $remembered) {
        $guard->shouldNotReceive('remember');

        return $guard;
    }

    $guard->expects('remember')->with($expected);

    return $guard;
}

function recordFor(ViewAttempt $attempt): Closure
{
    return fn (ViewRecord $record): bool => $record->viewableId === 7
        && $record->viewableType === Post::class
        && $record->visitor === 'visitor_one'
        && $record->collection === 'custom'
        && $record->viewedAt->equalTo(Carbon::now())
        && $record->viewerType === null
        && $record->viewerId === null
        && $record->context === null;
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
    $events->allows('hasListeners')->andReturn(false);
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
    $events->allows('hasListeners')->andReturn(false);
    $events->allows('dispatch');

    expect(recorder([rememberingGuard(false), guardThat(false)], Mockery::mock(BusDispatcher::class), Mockery::mock(RecordsViews::class), events: $events)->record($attempt)->recorded)->toBeFalse();
});

describe('attempted', function (): void {
    it('dispatches the stored view to whoever listens', function (): void {
        $attempt = attempt(queue: false);

        $action = Mockery::mock(RecordsViews::class);
        $action->allows('handle');

        $events = listeningEvents(fn (ViewAttempted $event): bool => $event->attempt === $attempt
            && $event->result->recorded
            && ! $event->result->queued);

        recorder([], Mockery::mock(BusDispatcher::class), $action, events: $events)->record($attempt);
    });

    it('dispatches the queued view in the request that queued it', function (): void {
        $attempt = attempt(queue: true);

        $bus = Mockery::mock(BusDispatcher::class);
        $bus->allows('dispatch');

        $events = listeningEvents(fn (ViewAttempted $event): bool => $event->result->queued);

        recorder([], $bus, Mockery::mock(RecordsViews::class), events: $events)->record($attempt);
    });

    it('dispatches the skipped view with the guard that refused it', function (): void {
        $attempt = attempt();
        $refusing = guardThat(false);

        $events = listeningEvents(fn (ViewAttempted $event): bool => $event->attempt === $attempt
            && ! $event->result->recorded
            && $event->result->skippedBy === $refusing);

        recorder([$refusing], Mockery::mock(BusDispatcher::class), Mockery::mock(RecordsViews::class), events: $events)->record($attempt);
    });

    it('hands the listener the attempt with the viewer resolved', function (): void {
        $viewer = new Apartment(['id' => 3]);
        $attempt = new ViewAttempt(new Post(['id' => 7]), identifyingVisitor($viewer));

        $action = Mockery::mock(RecordsViews::class);
        $action->allows('handle');

        $events = listeningEvents(fn (ViewAttempted $event): bool => $event->attempt->viewer === $viewer);

        recorder([], Mockery::mock(BusDispatcher::class), $action, events: $events, viewerEnabled: true)->record($attempt);
    });
});

describe('viewer', function (): void {
    it('records a guest view when nothing names a viewer', function (): void {
        $attempt = attempt();

        $action = recordingAction(fn (ViewRecord $record): bool => $record->viewerType === null && $record->viewerId === null);

        recorder([], Mockery::mock(BusDispatcher::class), $action, viewerEnabled: true)->record($attempt);
    });

    it('records the viewer the attempt names', function (): void {
        $visitor = Mockery::mock(Visitor::class);
        $visitor->allows('id')->andReturn('visitor_one');
        $attempt = new ViewAttempt(new Post(['id' => 7]), $visitor, viewer: new Apartment(['id' => 3]));

        $action = recordingAction(fn (ViewRecord $record): bool => $record->viewerType === Apartment::class && $record->viewerId === 3);

        recorder([], Mockery::mock(BusDispatcher::class), $action)->record($attempt);
    });

    it('asks the visitor for the viewer when enabled', function (): void {
        $attempt = new ViewAttempt(new Post(['id' => 7]), identifyingVisitor(new Apartment(['id' => 3])));

        $action = recordingAction(fn (ViewRecord $record): bool => $record->viewerType === Apartment::class && $record->viewerId === 3);

        recorder([], Mockery::mock(BusDispatcher::class), $action, viewerEnabled: true)->record($attempt);
    });

    it('keeps a string viewer key as a string', function (): void {
        $viewer = new class extends Apartment
        {
            protected $keyType = 'string';

            public $incrementing = false;
        };
        $viewer->setAttribute('id', 'uuid-three');
        $visitor = Mockery::mock(Visitor::class);
        $visitor->allows('id')->andReturn('visitor_one');
        $attempt = new ViewAttempt(new Post(['id' => 7]), $visitor, viewer: $viewer);

        $action = recordingAction(fn (ViewRecord $record): bool => $record->viewerType === $viewer->getMorphClass() && $record->viewerId === 'uuid-three');

        recorder([], Mockery::mock(BusDispatcher::class), $action)->record($attempt);
    });

    it('records a guest view when the visitor reports no viewer', function (): void {
        $attempt = new ViewAttempt(new Post(['id' => 7]), identifyingVisitor(null));

        $action = recordingAction(fn (ViewRecord $record): bool => $record->viewerType === null && $record->viewerId === null);

        recorder([], Mockery::mock(BusDispatcher::class), $action, viewerEnabled: true)->record($attempt);
    });

    it('does not ask the visitor for the viewer when disabled', function (): void {
        $visitor = Mockery::mock(Visitor::class);
        $visitor->allows('id')->andReturn('visitor_one');
        $visitor->shouldNotReceive('viewer');
        $attempt = new ViewAttempt(new Post(['id' => 7]), $visitor);

        $action = recordingAction(fn (ViewRecord $record): bool => $record->viewerType === null && $record->viewerId === null);

        recorder([], Mockery::mock(BusDispatcher::class), $action)->record($attempt);
    });

    it('prefers the viewer the attempt names over the one the visitor reports', function (): void {
        $attempt = new ViewAttempt(new Post(['id' => 7]), identifyingVisitor(new Apartment(['id' => 3])), viewer: new Post(['id' => 9]));

        $action = recordingAction(fn (ViewRecord $record): bool => $record->viewerType === Post::class && $record->viewerId === 9);

        recorder([], Mockery::mock(BusDispatcher::class), $action, viewerEnabled: true)->record($attempt);
    });

    it('refuses a viewer without a usable key', function (): void {
        $visitor = Mockery::mock(Visitor::class);
        $visitor->allows('id')->andReturn('visitor_one');
        $attempt = new ViewAttempt(new Post(['id' => 7]), $visitor, viewer: new Apartment);

        $action = Mockery::mock(RecordsViews::class);
        $action->shouldNotReceive('handle');

        expect(fn (): RecordResult => recorder([], Mockery::mock(BusDispatcher::class), $action)->record($attempt))
            ->toThrow(InvalidViewer::class, 'The key of the viewer ['.Apartment::class.'] must be an integer or a string, null given.');
    });

    it('hands the guards the attempt with the viewer resolved', function (): void {
        $viewer = new Apartment(['id' => 3]);
        $attempt = new ViewAttempt(new Post(['id' => 7]), identifyingVisitor($viewer));

        $guard = Mockery::mock(RecordingGuard::class);
        $guard->expects('allows')->with(Mockery::on(fn (ViewAttempt $seen): bool => $seen->viewer === $viewer && $seen->visitor === $attempt->visitor))->andReturn(true);

        $action = Mockery::mock(RecordsViews::class);
        $action->allows('handle');

        recorder([$guard], Mockery::mock(BusDispatcher::class), $action, viewerEnabled: true)->record($attempt);
    });

    it('keeps the cookie id as the visitor by default', function (): void {
        $attempt = new ViewAttempt(new Post(['id' => 7]), identifyingVisitor(new Apartment(['id' => 3])));

        $action = recordingAction(fn (ViewRecord $record): bool => $record->visitor === 'visitor_one' && $record->viewerId === 3);

        recorder([], Mockery::mock(BusDispatcher::class), $action, viewerEnabled: true)->record($attempt);
    });

    it('derives the visitor from the viewer when the identity is the viewer', function (): void {
        $viewer = new Apartment(['id' => 3]);
        $attempt = new ViewAttempt(new Post(['id' => 7]), identifyingVisitor($viewer));
        $expected = hash_hmac('sha256', Apartment::class.'|3', RECORDER_KEY);

        $action = recordingAction(fn (ViewRecord $record): bool => $record->visitor === $expected && $record->viewerId === 3);

        recorder([], Mockery::mock(BusDispatcher::class), $action, viewerEnabled: true, identity: 'viewer')->record($attempt);
    });

    it('falls back to the cookie id for a guest when the identity is the viewer', function (): void {
        $attempt = new ViewAttempt(new Post(['id' => 7]), identifyingVisitor(null));

        $action = recordingAction(fn (ViewRecord $record): bool => $record->visitor === 'visitor_one' && $record->viewerId === null);

        recorder([], Mockery::mock(BusDispatcher::class), $action, viewerEnabled: true, identity: 'viewer')->record($attempt);
    });
});

it('records the context the attempt carries', function (): void {
    $visitor = Mockery::mock(Visitor::class);
    $visitor->allows('id')->andReturn('visitor_one');
    $attempt = new ViewAttempt(new Post(['id' => 7]), $visitor, context: ['source' => 'newsletter']);

    $action = recordingAction(fn (ViewRecord $record): bool => $record->context === ['source' => 'newsletter']);

    recorder([], Mockery::mock(BusDispatcher::class), $action)->record($attempt);
});

it('does not remember a view the action fails to store', function (): void {
    $attempt = attempt();

    $action = Mockery::mock(RecordsViews::class);
    $action->expects('handle')->andThrow(new RuntimeException('The store is down.'));

    expect(fn (): RecordResult => recorder([rememberingGuard(false)], Mockery::mock(BusDispatcher::class), $action)->record($attempt))
        ->toThrow(RuntimeException::class, 'The store is down.');
});
