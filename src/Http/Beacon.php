<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Http;

use CyrildeWit\EloquentViewable\Contracts\Viewable;
use CyrildeWit\EloquentViewable\Exceptions\InvalidConfiguration;
use CyrildeWit\EloquentViewable\Exceptions\InvalidViewable;
use CyrildeWit\EloquentViewable\Support\Config;
use CyrildeWit\EloquentViewable\Support\ViewableKey;
use Illuminate\Routing\UrlGenerator;
use Illuminate\Support\HtmlString;

/**
 * Builds the signed URL a page posts to once it has loaded, and the script
 * that posts it.
 *
 * The URL is the same for every visitor and never expires, so it can live in
 * a page that is cached for any length of time. It is signed without the host,
 * so it still validates behind a proxy that reaches the app under another
 * host name.
 */
final readonly class Beacon
{
    public const string RouteName = 'eloquent-viewable.beacon';

    public const string PresenceRouteName = 'eloquent-viewable.presence';

    public const string LeaveRouteName = 'eloquent-viewable.presenceLeave';

    public function __construct(
        private UrlGenerator $urls,
        private Config $config,
    ) {}

    /**
     * @throws InvalidConfiguration
     * @throws InvalidViewable
     */
    public function url(
        Viewable $viewable,
        ?string $collection = null,
        ?int $cooldown = null,
        ?bool $queue = null,
    ): string {
        if (! $this->config->beaconEnabled()) {
            throw InvalidConfiguration::beaconDisabled();
        }

        $parameters = $this->parameters($viewable, $collection);

        if ($cooldown !== null) {
            $parameters['cooldown'] = $cooldown;
        }

        if ($queue !== null) {
            $parameters['queue'] = $queue ? 1 : 0;
        }

        return $this->urls->signedRoute(self::RouteName, $parameters, absolute: false);
    }

    /**
     * The URL a page posts to while it is open, to keep the visitor active.
     *
     * @throws InvalidConfiguration
     * @throws InvalidViewable
     */
    public function presenceUrl(Viewable $viewable, ?string $collection = null): string
    {
        $this->ensurePresenceIsEnabled();

        return $this->urls->signedRoute(self::PresenceRouteName, $this->parameters($viewable, $collection), absolute: false);
    }

    /**
     * The URL a page posts to as it closes, to stop counting the visitor.
     *
     * @throws InvalidConfiguration
     * @throws InvalidViewable
     */
    public function leaveUrl(Viewable $viewable, ?string $collection = null): string
    {
        $this->ensurePresenceIsEnabled();

        return $this->urls->signedRoute(self::LeaveRouteName, $this->parameters($viewable, $collection), absolute: false);
    }

    /**
     * A prerendered page waits until it is shown, so a page the browser only
     * prepared in case it is visited records nothing.
     *
     * A live script also keeps the visitor active: once the view is posted it
     * sends a heartbeat every `presence.heartbeat` seconds while the page is
     * visible, pauses while it is hidden, and lets the visitor go when the
     * page is closed. When the heartbeat answers with a count, it is written
     * into every `[data-views-live]` element and a `views:live` event is
     * dispatched on the document.
     *
     * @throws InvalidConfiguration
     * @throws InvalidViewable
     */
    public function script(
        Viewable $viewable,
        ?string $collection = null,
        ?int $cooldown = null,
        ?bool $queue = null,
        bool $live = false,
    ): HtmlString {
        $url = $this->json($this->url($viewable, $collection, $cooldown, $queue));

        if ($live) {
            return $this->liveScript($url, $this->json([
                'heartbeat' => $this->presenceUrl($viewable, $collection),
                'leave' => $this->leaveUrl($viewable, $collection),
                'interval' => $this->config->presenceHeartbeat() * 1000,
            ]));
        }

        return new HtmlString(<<<HTML
            <script>
            (function (url) {
                var send = function () {
                    if (navigator.sendBeacon && navigator.sendBeacon(url)) return;
                    fetch(url, { method: 'POST', keepalive: true, credentials: 'same-origin' });
                };
                if (document.prerendering) {
                    document.addEventListener('prerenderingchange', send, { once: true });
                } else {
                    send();
                }
            })({$url});
            </script>
            HTML);
    }

    /**
     * The view is posted with `fetch` rather than `sendBeacon`, so the first
     * heartbeat waits for it and carries the visitor cookie it may set. A
     * page restored from the back-forward cache starts beating again.
     */
    private function liveScript(string $url, string $live): HtmlString
    {
        return new HtmlString(<<<HTML
            <script>
            (function (url, live) {
                var timer = null;
                var post = function (target) {
                    return fetch(target, { method: 'POST', keepalive: true, credentials: 'same-origin' });
                };
                var show = function (response) {
                    if (response.status !== 200) return;
                    response.json().then(function (data) {
                        document.querySelectorAll('[data-views-live]').forEach(function (element) {
                            element.textContent = data.active;
                        });
                        document.dispatchEvent(new CustomEvent('views:live', { detail: data }));
                    });
                };
                var beat = function () {
                    post(live.heartbeat).then(show, function () {});
                };
                var resume = function () {
                    if (timer !== null || document.hidden) return;
                    beat();
                    timer = setInterval(beat, live.interval);
                };
                var pause = function () {
                    if (timer === null) return;
                    clearInterval(timer);
                    timer = null;
                };
                var leave = function () {
                    pause();
                    if (navigator.sendBeacon && navigator.sendBeacon(live.leave)) return;
                    post(live.leave);
                };
                var start = function () {
                    post(url).then(resume, resume);
                    document.addEventListener('visibilitychange', function () {
                        if (document.hidden) {
                            pause();
                        } else {
                            resume();
                        }
                    });
                    window.addEventListener('pagehide', leave);
                    window.addEventListener('pageshow', function (event) {
                        if (event.persisted) resume();
                    });
                };
                if (document.prerendering) {
                    document.addEventListener('prerenderingchange', start, { once: true });
                } else {
                    start();
                }
            })({$url}, {$live});
            </script>
            HTML);
    }

    /**
     * @return array<string, int|string>
     *
     * @throws InvalidViewable
     */
    private function parameters(Viewable $viewable, ?string $collection): array
    {
        $key = ViewableKey::of($viewable);

        if ($key === null) {
            throw InvalidViewable::beaconWithoutKey($viewable::class);
        }

        $parameters = [
            'type' => $viewable->getMorphClass(),
            'key' => $key,
        ];

        if ($collection !== null) {
            $parameters['collection'] = $collection;
        }

        return $parameters;
    }

    /** @throws InvalidConfiguration */
    private function ensurePresenceIsEnabled(): void
    {
        if (! $this->config->beaconEnabled()) {
            throw InvalidConfiguration::beaconDisabled();
        }

        if (! $this->config->presenceEnabled()) {
            throw InvalidConfiguration::presenceDisabled();
        }
    }

    /**
     * Escaped so that no value can close the script tag it is printed in.
     *
     * @param  array<string, int|string>|string  $value
     */
    private function json(array|string $value): string
    {
        return json_encode(
            $value,
            JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR,
        );
    }
}
