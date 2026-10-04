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

        if ($cooldown !== null) {
            $parameters['cooldown'] = $cooldown;
        }

        if ($queue !== null) {
            $parameters['queue'] = $queue ? 1 : 0;
        }

        return $this->urls->signedRoute(self::RouteName, $parameters, absolute: false);
    }

    /**
     * A prerendered page waits until it is shown, so a page the browser only
     * prepared in case it is visited records nothing.
     *
     * @throws InvalidConfiguration
     * @throws InvalidViewable
     */
    public function script(
        Viewable $viewable,
        ?string $collection = null,
        ?int $cooldown = null,
        ?bool $queue = null,
    ): HtmlString {
        $url = json_encode(
            $this->url($viewable, $collection, $cooldown, $queue),
            JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR,
        );

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
}
