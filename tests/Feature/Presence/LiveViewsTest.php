<?php

declare(strict_types=1);

use Carbon\Carbon;
use Carbon\CarbonInterval;
use CyrildeWit\EloquentViewable\Exceptions\InvalidConfiguration;
use CyrildeWit\EloquentViewable\Exceptions\InvalidViewable;
use CyrildeWit\EloquentViewable\Facades\Views;
use CyrildeWit\EloquentViewable\Presence\Contracts\PresenceStore;
use CyrildeWit\EloquentViewable\Presence\Data\Reference;
use CyrildeWit\EloquentViewable\Presence\Data\Sighting;
use CyrildeWit\EloquentViewable\Presence\Exceptions\InvalidWindow;
use CyrildeWit\EloquentViewable\Presence\LiveViews;
use CyrildeWit\EloquentViewable\Querying\Exceptions\InvalidLimit;
use CyrildeWit\EloquentViewable\Querying\Ranking\Entry;
use CyrildeWit\EloquentViewable\Querying\Ranking\Ranking;
use CyrildeWit\EloquentViewable\Recording\Guards\EnforceCooldown;
use CyrildeWit\EloquentViewable\Tests\Fixtures\Guards\RefuseAll;
use CyrildeWit\EloquentViewable\Tests\Fixtures\Models\Apartment;
use CyrildeWit\EloquentViewable\Tests\Fixtures\Models\Post;
use CyrildeWit\EloquentViewable\Tests\Fixtures\Models\User;
use CyrildeWit\EloquentViewable\Visitors\Contracts\Visitor;
use Illuminate\Support\Collection;

/**
 * Presence is kept in memory, so every test starts with nobody looking.
 *
 * @param  array<string, mixed>  $config
 */
function enablePresence(array $config = []): void
{
    config()->set('eloquent-viewable.presence', [
        ...config('eloquent-viewable.presence'),
        'enabled' => true,
        'driver' => 'array',
        ...$config,
    ]);
}

/**
 * A visitor of their own for each view, the way separate browsers are.
 */
function liveVisitor(string $id, ?User $viewer = null): Visitor
{
    $visitor = Mockery::mock(Visitor::class);
    $visitor->allows('id')->andReturn($id);
    $visitor->allows('viewer')->andReturn($viewer);
    $visitor->allows('ip')->andReturn('127.0.0.1');
    $visitor->allows('userAgent')->andReturn('Mozilla/5.0');
    $visitor->allows('hasDoNotTrackHeader')->andReturn(false);
    $visitor->allows('hasGlobalPrivacyControl')->andReturn(false);
    $visitor->allows('isPrefetch')->andReturn(false);
    $visitor->allows('isHeadRequest')->andReturn(false);

    return $visitor;
}

beforeEach(function (): void {
    Carbon::setTestNow('2026-10-06 12:00:00');

    $this->post = Post::factory()->create();
});

it('refuses to read while presence is off', function (): void {
    expect(fn (): int => views($this->post)->activeVisitors())
        ->toThrow(InvalidConfiguration::class, 'Presence is not kept. Set `eloquent-viewable.presence.enabled` to `true`');
});

it('records nothing extra while presence is off', function (): void {
    expect(views($this->post)->attempt()->present)->toBeFalse()
        ->and(views($this->post)->heartbeat())->toBeFalse();

    views($this->post)->leave();
});

describe('counting', function (): void {
    beforeEach(fn () => enablePresence());

    it('counts the visitors of a viewable, its type and the site', function (): void {
        $other = Post::factory()->create();
        $apartment = Apartment::factory()->create();

        views($this->post)->useVisitor(liveVisitor('one'))->record();
        views($this->post)->useVisitor(liveVisitor('two'))->record();
        views($other)->useVisitor(liveVisitor('two'))->record();
        views($apartment)->useVisitor(liveVisitor('three'))->record();

        expect(views($this->post)->activeVisitors())->toBe(2)
            ->and(views($other)->live()->count())->toBe(1)
            ->and(views(Post::class)->live()->count())->toBe(2)
            ->and(Views::live()->count())->toBe(3);
    });

    it('counts the visitors of one collection', function (): void {
        views($this->post)->useVisitor(liveVisitor('one'))->collection('amp')->record();
        views($this->post)->useVisitor(liveVisitor('two'))->record();

        expect(views($this->post)->collection('amp')->activeVisitors())->toBe(1)
            ->and(views($this->post)->activeVisitors())->toBe(2)
            ->and(Views::collection('amp')->live()->count())->toBe(1);
    });

    it('keeps a visitor active while their cooldown skips the view', function (): void {
        config()->set('eloquent-viewable.recording.guards', [EnforceCooldown::class]);
        config()->set('eloquent-viewable.cooldown.store', 'cache');

        expect(views($this->post)->cooldown(5)->record())->toBeTrue();

        Carbon::setTestNow('2026-10-06 12:04:00');

        $result = views($this->post)->cooldown(5)->attempt();

        Carbon::setTestNow('2026-10-06 12:08:00');

        expect($result->wasSkippedBy(EnforceCooldown::class))->toBeTrue()
            ->and($result->present)->toBeTrue()
            ->and($this->post)->toHaveViewsCount(1)
            ->and(views($this->post)->activeVisitors())->toBe(1);
    });

    it('does not keep a visitor a guard refuses', function (): void {
        config()->set('eloquent-viewable.recording.guards', [RefuseAll::class]);

        expect(views($this->post)->attempt()->present)->toBeFalse()
            ->and(views($this->post)->heartbeat())->toBeFalse()
            ->and(views($this->post)->activeVisitors())->toBe(0);
    });

    it('lets a visitor go once the window has passed', function (): void {
        views($this->post)->record();

        Carbon::setTestNow('2026-10-06 12:04:59');

        expect(views($this->post)->activeVisitors())->toBe(1);

        Carbon::setTestNow('2026-10-06 12:05:00');

        expect(views($this->post)->activeVisitors())->toBe(0);
    });

    it('keeps a visitor active with a heartbeat and lets them leave', function (): void {
        views($this->post)->record();

        Carbon::setTestNow('2026-10-06 12:04:00');

        expect(views($this->post)->heartbeat())->toBeTrue();

        Carbon::setTestNow('2026-10-06 12:08:00');

        expect(views($this->post)->activeVisitors())->toBe(1)
            ->and($this->post)->toHaveViewsCount(1);

        views($this->post)->leave();

        expect(views($this->post)->activeVisitors())->toBe(0);
    });

    it('narrows the window for one read', function (): void {
        views($this->post)->useVisitor(liveVisitor('one'))->record();

        Carbon::setTestNow('2026-10-06 12:02:00');

        views($this->post)->useVisitor(liveVisitor('two'))->record();

        expect(views($this->post)->live()->within(60)->count())->toBe(1)
            ->and(views($this->post)->live()->within(CarbonInterval::minutes(3))->count())->toBe(2)
            ->and(views($this->post)->live()->count())->toBe(2);
    });

    it('refuses a window it does not keep', function (int $seconds): void {
        expect(fn (): LiveViews => views($this->post)->live()->within($seconds))
            ->toThrow(InvalidWindow::class, "within() needs between 1 and 300 seconds, the `presence.window` presence is kept for, {$seconds} given.");
    })->with([0, 301]);

    it('counts a set of viewables by their key', function (): void {
        $other = Post::factory()->create();
        $idle = Post::factory()->create();

        views($this->post)->useVisitor(liveVisitor('one'))->record();
        views($this->post)->useVisitor(liveVisitor('two'))->record();
        views($other)->useVisitor(liveVisitor('one'))->collection('amp')->record();

        expect(Views::forViewables([$this->post, $other, $idle])->live()->counts()->all())->toBe([
            $this->post->getKey() => 2,
            $other->getKey() => 1,
            $idle->getKey() => 0,
        ])
            ->and(Views::forViewables([$this->post, $other])->collection('amp')->live()->counts()->all())->toBe([
                $this->post->getKey() => 0,
                $other->getKey() => 1,
            ]);
    });

    it('asks for counts() on a set and a set for counts()', function (): void {
        expect(fn (): int => Views::forViewables([$this->post])->live()->count())
            ->toThrow(InvalidViewable::class, 'A set of viewables is counted one by one. Call counts() instead of count().')
            ->and(fn (): Collection => views($this->post)->live()->counts())
            ->toThrow(InvalidViewable::class, 'No viewables were given. Call forViewables() before counting them.');
    });
});

describe('ranking', function (): void {
    beforeEach(fn () => enablePresence());

    it('ranks what is looked at right now', function (): void {
        $busy = Post::factory()->create();
        $apartment = Apartment::factory()->create();

        views($this->post)->useVisitor(liveVisitor('one'))->record();
        views($busy)->useVisitor(liveVisitor('one'))->record();
        views($busy)->useVisitor(liveVisitor('two'))->record();
        views($busy)->useVisitor(liveVisitor('three'))->record();
        views($apartment)->useVisitor(liveVisitor('one'))->record();
        views($apartment)->useVisitor(liveVisitor('two'))->record();

        $ranking = Views::live()->top();

        expect($ranking->entries->map(fn (Entry $entry): array => [$entry->viewable::class, $entry->viewable->getKey(), $entry->count, $entry->rank])->all())->toBe([
            [Post::class, $busy->getKey(), 3, 1],
            [Apartment::class, $apartment->getKey(), 2, 2],
            [Post::class, $this->post->getKey(), 1, 3],
        ])
            ->and(views(Post::class)->live()->top()->viewables()->modelKeys())->toBe([$busy->getKey(), $this->post->getKey()])
            ->and(Views::live()->top(1)->count())->toBe(1);
    });

    it('ranks within one collection and leaves out what nobody is looking at', function (): void {
        $other = Post::factory()->create();

        views($this->post)->useVisitor(liveVisitor('one'))->record();
        views($other)->useVisitor(liveVisitor('two'))->collection('amp')->record();
        views($other)->useVisitor(liveVisitor('three'))->record();
        views($other)->useVisitor(liveVisitor('three'))->leave();

        expect(Views::collection('amp')->live()->top()->viewables()->modelKeys())->toBe([$other->getKey()]);

        views($other)->useVisitor(liveVisitor('two'))->leave();

        expect(Views::live()->top()->viewables()->modelKeys())->toBe([$this->post->getKey()]);
    });

    it('ranks nothing when nobody is looking', function (): void {
        expect(Views::live()->top()->isEmpty())->toBeTrue();
    });

    it('refuses to rank one viewable or fewer than one', function (): void {
        expect(fn (): Ranking => views($this->post)->live()->top())
            ->toThrow(InvalidViewable::class, 'top() ranks every viewable of a type or every type.')
            ->and(fn () => Views::live()->top(0))
            ->toThrow(InvalidLimit::class, 'live()->top() needs a limit of at least one, 0 given.');
    });

    it('only ranks the most recently seen candidates', function (): void {
        enablePresence(['max_candidates' => 1]);

        $other = Post::factory()->create();

        views($this->post)->useVisitor(liveVisitor('one'))->record();
        views($this->post)->useVisitor(liveVisitor('two'))->record();

        Carbon::setTestNow('2026-10-06 12:01:00');

        views($other)->useVisitor(liveVisitor('three'))->record();

        expect(Views::live()->top()->viewables()->modelKeys())->toBe([$other->getKey()]);
    });
});

describe('viewers', function (): void {
    beforeEach(fn () => enablePresence(['viewers' => true]));

    it('lists the signed-in viewers looking right now, the most recent first', function (): void {
        $ann = User::factory()->create();
        $bob = User::factory()->create();

        views($this->post)->useVisitor(liveVisitor('one', $ann))->viewedBy($ann)->record();

        Carbon::setTestNow('2026-10-06 12:01:00');

        views($this->post)->useVisitor(liveVisitor('two', $bob))->viewedBy($bob)->record();
        views($this->post)->useVisitor(liveVisitor('three'))->record();

        expect(views($this->post)->live()->viewers()->modelKeys())->toBe([$bob->getKey(), $ann->getKey()])
            ->and(views($this->post)->live()->viewers(1)->modelKeys())->toBe([$bob->getKey()])
            ->and(Views::live()->viewers()->modelKeys())->toBe([$bob->getKey(), $ann->getKey()])
            ->and(views($this->post)->activeVisitors())->toBe(3);
    });

    it('skips viewers that are gone or not models', function (): void {
        $ann = User::factory()->create();

        app(PresenceStore::class)->touch(new Sighting($this->post->getMorphClass(), $this->post->getKey(), 'one', Carbon::now(), viewer: new Reference(User::class, $ann->getKey())));
        app(PresenceStore::class)->touch(new Sighting($this->post->getMorphClass(), $this->post->getKey(), 'two', Carbon::now(), viewer: new Reference(User::class, 999_999)));
        app(PresenceStore::class)->touch(new Sighting($this->post->getMorphClass(), $this->post->getKey(), 'three', Carbon::now(), viewer: new Reference(stdClass::class, 1)));

        expect(views($this->post)->live()->viewers()->modelKeys())->toBe([$ann->getKey()]);
    });

    it('refuses to list viewers it does not keep or fewer than one', function (): void {
        expect(fn (): Illuminate\Database\Eloquent\Collection => views($this->post)->live()->viewers(0))
            ->toThrow(InvalidLimit::class, 'live()->viewers() needs a limit of at least one, 0 given.');

        enablePresence(['viewers' => false]);

        expect(fn (): Illuminate\Database\Eloquent\Collection => views($this->post)->live()->viewers())
            ->toThrow(InvalidConfiguration::class, 'The signed-in viewers are not kept.');
    });
});
