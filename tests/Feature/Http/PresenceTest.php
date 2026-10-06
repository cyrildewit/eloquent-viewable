<?php

declare(strict_types=1);

use Carbon\Carbon;
use CyrildeWit\EloquentViewable\EloquentViewableServiceProvider;
use CyrildeWit\EloquentViewable\Exceptions\InvalidConfiguration;
use CyrildeWit\EloquentViewable\Exceptions\InvalidViewable;
use CyrildeWit\EloquentViewable\Http\Beacon;
use CyrildeWit\EloquentViewable\Models\View;
use CyrildeWit\EloquentViewable\Presence\Contracts\PresenceStore;
use CyrildeWit\EloquentViewable\Presence\Data\Sighting;
use CyrildeWit\EloquentViewable\Tests\Fixtures\Models\Post;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Route;

/**
 * The routes are registered at boot, so the provider boots again once the
 * config is set. Presence is kept in memory.
 *
 * @param  array<string, mixed>  $presence
 */
function enableLiveBeacon(array $presence = [], bool $beacon = true): void
{
    config()->set('eloquent-viewable.recording.beacon', [
        ...config('eloquent-viewable.recording.beacon'),
        'enabled' => $beacon,
        'middleware' => [],
    ]);

    config()->set('eloquent-viewable.presence', [
        ...config('eloquent-viewable.presence'),
        'enabled' => true,
        'driver' => 'array',
        ...$presence,
    ]);

    app()->getProvider(EloquentViewableServiceProvider::class)?->boot();
}

beforeEach(function (): void {
    Carbon::setTestNow('2026-10-06 12:00:00');

    $this->post = Post::factory()->create();
    $this->beacon = app(Beacon::class);
});

describe('routes', function (): void {
    it('are not registered while presence is off', function (): void {
        config()->set('eloquent-viewable.recording.beacon.enabled', true);
        app()->getProvider(EloquentViewableServiceProvider::class)?->boot();

        expect(Route::has(Beacon::RouteName))->toBeTrue()
            ->and(Route::has(Beacon::PresenceRouteName))->toBeFalse()
            ->and(Route::has(Beacon::LeaveRouteName))->toBeFalse();
    });

    it('are registered next to the beacon', function (): void {
        enableLiveBeacon();

        expect(Route::getRoutes()->getByName(Beacon::PresenceRouteName)->uri())->toBe('_ev/presence/{type}/{key}')
            ->and(Route::getRoutes()->getByName(Beacon::LeaveRouteName)->uri())->toBe('_ev/presence/{type}/{key}/leave')
            ->and(Route::getRoutes()->getByName(Beacon::LeaveRouteName)->methods())->toBe(['POST']);
    });
});

describe('heartbeat', function (): void {
    beforeEach(fn () => enableLiveBeacon());

    it('keeps the visitor active without recording a view', function (): void {
        $this->post($this->beacon->presenceUrl($this->post))
            ->assertNoContent()
            ->assertHeader('Cache-Control', 'no-store, private');

        expect(View::count())->toBe(0)
            ->and(views($this->post)->activeVisitors())->toBe(1);
    });

    it('keeps the visitor in the collection the url names', function (): void {
        $this->post($this->beacon->presenceUrl($this->post, collection: 'amp'))->assertNoContent();

        expect(views($this->post)->collection('amp')->activeVisitors())->toBe(1);
    });

    it('answers with the active visitors when asked to', function (): void {
        enableLiveBeacon(['expose_count' => true]);

        app(PresenceStore::class)->touch(new Sighting($this->post->getMorphClass(), $this->post->getKey(), 'other', Carbon::now()));

        $this->post($this->beacon->presenceUrl($this->post))
            ->assertOk()
            ->assertExactJson(['active' => 2])
            ->assertHeader('Cache-Control', 'no-store, private');
    });

    it('answers the same when a guard refuses the visitor', function (): void {
        enableLiveBeacon(['expose_count' => true]);

        $this->post($this->beacon->presenceUrl($this->post), headers: ['User-Agent' => 'Googlebot/2.1'])
            ->assertOk()
            ->assertExactJson(['active' => 0]);
    });

    it('refuses a url that is not signed or a model that is gone', function (): void {
        $this->post("/_ev/presence/post/{$this->post->getKey()}")->assertForbidden();

        $url = $this->beacon->presenceUrl($this->post);

        $this->post->delete();

        $this->post($url)->assertNotFound();
    });
});

describe('leave', function (): void {
    beforeEach(fn () => enableLiveBeacon());

    it('stops counting the visitor at once', function (): void {
        $this->post($this->beacon->presenceUrl($this->post))->assertNoContent();
        $this->post($this->beacon->leaveUrl($this->post))->assertNoContent();

        expect(views($this->post)->activeVisitors())->toBe(0);
    });

    it('lets the visitor leave one collection', function (): void {
        $this->post($this->beacon->presenceUrl($this->post, collection: 'amp'))->assertNoContent();
        $this->post($this->beacon->leaveUrl($this->post, collection: 'amp'))->assertNoContent();

        expect(views($this->post)->collection('amp')->activeVisitors())->toBe(0);
    });

    it('refuses a url that is not signed or a model that is gone', function (): void {
        $this->post("/_ev/presence/post/{$this->post->getKey()}/leave")->assertForbidden();

        $url = $this->beacon->leaveUrl($this->post);

        $this->post->delete();

        $this->post($url)->assertNotFound();
    });
});

describe('urls', function (): void {
    it('need the beacon and presence', function (string $method): void {
        enableLiveBeacon(beacon: false);

        expect(fn (): string => $this->beacon->{$method}($this->post))
            ->toThrow(InvalidConfiguration::class, 'Set `eloquent-viewable.recording.beacon.enabled` to `true`');

        enableLiveBeacon(['enabled' => false]);

        expect(fn (): string => $this->beacon->{$method}($this->post))
            ->toThrow(InvalidConfiguration::class, 'Set `eloquent-viewable.presence.enabled` to `true`');
    })->with(['presenceUrl', 'leaveUrl']);

    it('need a saved model', function (): void {
        enableLiveBeacon();

        $this->beacon->presenceUrl(new Post);
    })->throws(InvalidViewable::class, 'an unsaved');

    it('are relative and the same on every call', function (): void {
        enableLiveBeacon();

        expect($this->beacon->presenceUrl($this->post, collection: 'amp'))
            ->toStartWith('/_ev/presence/')
            ->toContain('collection=amp', 'signature=')
            ->toBe($this->beacon->presenceUrl($this->post, collection: 'amp'))
            ->and($this->beacon->leaveUrl($this->post))->toContain('/leave?');
    });

    it('cannot be used for another model', function (): void {
        enableLiveBeacon();

        $other = Post::factory()->create();
        $url = str_replace("/{$this->post->getKey()}?", "/{$other->getKey()}?", $this->beacon->presenceUrl($this->post));

        $this->post($url)->assertForbidden();
    });
});

describe('live script', function (): void {
    beforeEach(fn () => enableLiveBeacon(['heartbeat' => 45]));

    it('posts the view, then beats while the page is visible and leaves when it closes', function (): void {
        $script = $this->beacon->script($this->post, collection: 'amp', live: true)->toHtml();

        $flags = JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES;
        $url = json_encode($this->beacon->url($this->post, collection: 'amp'), $flags);
        $live = json_encode([
            'heartbeat' => $this->beacon->presenceUrl($this->post, collection: 'amp'),
            'leave' => $this->beacon->leaveUrl($this->post, collection: 'amp'),
            'interval' => 45_000,
        ], $flags);

        expect($script)->toStartWith('<script>')
            ->toContain("})({$url}, {$live});", 'visibilitychange', 'pagehide', 'pageshow', '[data-views-live]', "'views:live'", 'prerenderingchange', 'post(url).then(resume, resume)')
            ->and(substr_count($script, '</script>'))->toBe(1);
    });

    it('is printed by the Blade directive', function (): void {
        $html = Blade::render('@viewsBeacon($post, live: true)', ['post' => $this->post]);

        expect($html)->toBe($this->beacon->script($this->post, live: true)->toHtml());
    });

    it('needs presence', function (): void {
        enableLiveBeacon(['enabled' => false]);

        $this->beacon->script($this->post, live: true);
    })->throws(InvalidConfiguration::class, 'Set `eloquent-viewable.presence.enabled` to `true`');
});
