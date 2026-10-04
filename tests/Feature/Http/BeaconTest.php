<?php

declare(strict_types=1);

use CyrildeWit\EloquentViewable\EloquentViewableServiceProvider;
use CyrildeWit\EloquentViewable\Exceptions\InvalidConfiguration;
use CyrildeWit\EloquentViewable\Exceptions\InvalidViewable;
use CyrildeWit\EloquentViewable\Http\Beacon;
use CyrildeWit\EloquentViewable\Models\View;
use CyrildeWit\EloquentViewable\Recording\Jobs\RecordViewJob;
use CyrildeWit\EloquentViewable\Tests\Fixtures\Models\Post;
use CyrildeWit\EloquentViewable\Tests\Fixtures\Models\User;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\URL;

/**
 * The route is registered at boot, so the provider boots again once the
 * config is set. The `web` group is left out unless a test asks for it, so
 * cooldowns keep the session between requests, as the middleware tests rely
 * on.
 *
 * @param  array<string, mixed>  $config
 */
function enableBeacon(array $config = []): void
{
    config()->set('eloquent-viewable.recording.beacon', [
        ...config('eloquent-viewable.recording.beacon'),
        'enabled' => true,
        'middleware' => [],
        ...$config,
    ]);

    app()->getProvider(EloquentViewableServiceProvider::class)?->boot();
}

function beaconUrl(mixed ...$arguments): string
{
    return app(Beacon::class)->url(...$arguments);
}

beforeEach(function (): void {
    $this->post = Post::factory()->create();
});

describe('route', function (): void {
    it('is not registered by default', function (): void {
        expect(Route::has(Beacon::RouteName))->toBeFalse();
    });

    it('is registered under the configured prefix and middleware', function (): void {
        enableBeacon(['prefix' => '/stats/beacon/', 'middleware' => ['web']]);

        $route = Route::getRoutes()->getByName(Beacon::RouteName);

        expect($route->uri())->toBe('stats/beacon/{type}/{key}')
            ->and($route->methods())->toBe(['POST'])
            ->and($route->middleware())->toBe(['web']);
    });
});

describe('recording', function (): void {
    beforeEach(fn () => enableBeacon());

    it('records a view of the model the url names', function (): void {
        $this->post(beaconUrl($this->post))
            ->assertNoContent()
            ->assertHeader('Cache-Control', 'no-store, private');

        expect($this->post)->toHaveViewsCount(1);
    });

    it('records through the web group', function (): void {
        enableBeacon(['middleware' => ['web']]);

        $this->post(beaconUrl($this->post))
            ->assertNoContent()
            ->assertCookie(config('eloquent-viewable.visitor.cookie.name'));

        expect($this->post)->toHaveViewsCount(1);
    });

    it('records into the given collection', function (): void {
        $this->post(beaconUrl($this->post, collection: 'amp'))->assertNoContent();

        expect(View::sole()->collection)->toBe('amp');
    });

    it('applies the given cooldown', function (): void {
        $url = beaconUrl($this->post, cooldown: 30);

        $this->post($url)->assertNoContent();
        $this->post($url)->assertNoContent();

        expect($this->post)->toHaveViewsCount(1);
    });

    it('queues the view when told to', function (bool $queue): void {
        Bus::fake();

        $this->post(beaconUrl($this->post, queue: $queue))->assertNoContent();

        Bus::assertDispatchedTimes(RecordViewJob::class, $queue ? 1 : 0);
    })->with([true, false]);

    it('leaves queueing to the config when the url does not say', function (): void {
        Bus::fake();
        config()->set('eloquent-viewable.recording.queue.enabled', true);

        $this->post(beaconUrl($this->post))->assertNoContent();

        Bus::assertDispatchedTimes(RecordViewJob::class, 1);
    });

    it('answers a view a guard skips the same as a recorded one', function (): void {
        $this->post(beaconUrl($this->post), headers: ['User-Agent' => 'Googlebot/2.1'])->assertNoContent();

        expect(View::count())->toBe(0);
    });

    it('resolves a morph alias', function (): void {
        Relation::morphMap(['post' => Post::class]);

        try {
            $url = beaconUrl($this->post);

            expect($url)->toStartWith('/_ev/post/');

            $this->post($url)->assertNoContent();

            expect($this->post)->toHaveViewsCount(1);
        } finally {
            Relation::morphMap([], false);
        }
    });
});

describe('refusing', function (): void {
    beforeEach(fn () => enableBeacon());

    it('refuses a url that is not signed', function (): void {
        $this->post("/_ev/post/{$this->post->getKey()}")->assertForbidden();

        expect(View::count())->toBe(0);
    });

    it('refuses a url whose options were changed', function (): void {
        $this->post(beaconUrl($this->post).'&collection=other')->assertForbidden();

        expect(View::count())->toBe(0);
    });

    it('refuses a get request', function (): void {
        $this->get(beaconUrl($this->post))->assertMethodNotAllowed();
    });

    it('answers not found for a model that is gone', function (): void {
        $url = beaconUrl($this->post);

        $this->post->delete();

        $this->post($url)->assertNotFound();
    });

    it('answers not found for a type that is not a viewable model', function (string $type): void {
        $url = URL::signedRoute(Beacon::RouteName, ['type' => $type, 'key' => 1], absolute: false);

        $this->post($url)->assertNotFound()->assertHeader('Cache-Control', 'no-store, private');
    })->with([
        'unknown class' => ['App\\Models\\Missing'],
        'not a model' => [stdClass::class],
        'not viewable' => [User::class],
    ]);
});

/*
 * Laravel skips its CSRF check while the environment is `testing`, so these
 * leave it for a moment. A cached page carries no token, so only the browser's
 * `Sec-Fetch-Site` header gets a beacon through.
 */
describe('forgery', function (): void {
    beforeEach(function (): void {
        enableBeacon(['middleware' => ['web']]);

        $this->app['env'] = 'production';
    });

    afterEach(function (): void {
        $this->app['env'] = 'testing';
    });

    it('accepts a beacon the browser sent from the same origin', function (): void {
        $this->post(beaconUrl($this->post), headers: ['Sec-Fetch-Site' => 'same-origin'])->assertNoContent();

        expect($this->post)->toHaveViewsCount(1);
    });

    it('refuses a beacon without proof of its origin', function (array $headers): void {
        $this->post(beaconUrl($this->post), headers: $headers)->assertStatus(419);

        expect(View::count())->toBe(0);
    })->with([
        'from another site' => [['Sec-Fetch-Site' => 'cross-site']],
        'from a sibling subdomain' => [['Sec-Fetch-Site' => 'same-site']],
        'from a browser that does not say' => [[]],
    ]);
});

describe('url', function (): void {
    it('needs the beacon to be enabled', function (): void {
        beaconUrl($this->post);
    })->throws(InvalidConfiguration::class, 'Set `eloquent-viewable.recording.beacon.enabled` to `true`');

    it('needs a saved model', function (): void {
        enableBeacon();

        beaconUrl(new Post);
    })->throws(InvalidViewable::class, 'an unsaved [CyrildeWit\EloquentViewable\Tests\Fixtures\Models\Post] was given');

    it('is relative and the same on every call', function (): void {
        enableBeacon();

        $url = beaconUrl($this->post, collection: 'amp', cooldown: 30, queue: false);

        expect($url)->toStartWith('/_ev/')
            ->toContain('collection=amp', 'cooldown=30', 'queue=0', 'signature=')
            ->and(beaconUrl($this->post, collection: 'amp', cooldown: 30, queue: false))->toBe($url);
    });
});

describe('script', function (): void {
    beforeEach(fn () => enableBeacon());

    it('posts the url once the page is shown', function (): void {
        $script = app(Beacon::class)->script($this->post)->toHtml();

        $url = json_encode(beaconUrl($this->post), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES);

        expect($script)->toStartWith('<script>')
            ->toContain("})({$url});", 'navigator.sendBeacon(url)', 'keepalive: true', 'prerenderingchange');
    });

    it('cannot be closed early by an option', function (): void {
        $script = app(Beacon::class)->script($this->post, collection: '</script><script>alert(1)')->toHtml();

        expect(substr_count($script, '</script>'))->toBe(1)
            ->and(substr_count($script, '<script>'))->toBe(1);
    });

    it('is compiled from the Blade directive', function (): void {
        expect(Blade::compileString('@viewsBeacon($post)'))
            ->toBe('<?php echo \Illuminate\Container\Container::getInstance()->make(\CyrildeWit\EloquentViewable\Http\Beacon::class)->script($post); ?>');
    });

    it('is printed by the Blade directive', function (): void {
        $html = Blade::render('@viewsBeacon($post, collection: \'amp\')', ['post' => $this->post]);

        expect($html)->toBe(app(Beacon::class)->script($this->post, collection: 'amp')->toHtml());
    });
});
