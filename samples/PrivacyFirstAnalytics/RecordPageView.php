<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Samples\PrivacyFirstAnalytics;

use Carbon\Carbon;
use CyrildeWit\EloquentViewable\Recording\Contracts\RecordingGuard;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RecordPageView
{
    /**
     * Hosts whose visitors arrived from a search. Matched on the host and
     * its subdomains, so `www.google.com` is a search and `notgoogle.com`
     * is not.
     */
    private const array SearchEngines = ['google.com', 'bing.com', 'duckduckgo.com', 'ecosia.org'];

    /**
     * The beacon a docs page sends once it is shown:
     * `navigator.sendBeacon('/api/docs/12/views', JSON.stringify({referrer: document.referrer}))`.
     */
    public function __invoke(Request $request, DocPage $page): JsonResponse
    {
        $result = views($page)
            // The fingerprint changes at midnight, so a cooldown running
            // until then counts each reader once per page per day.
            ->cooldown(Carbon::tomorrow())
            ->context(['source' => $this->source($request->string('referrer')->toString(), $request->getHost())])
            ->attempt();

        // `sendBeacon()` ignores the response. It is there for whoever opens
        // the network tab to find out why their own visit did not count.
        return new JsonResponse([
            'recorded' => $result->recorded,
            'skipped_by' => $result->skippedBy instanceof RecordingGuard ? class_basename($result->skippedBy) : null,
        ]);
    }

    /**
     * Where the reader came from, as one of four words. The referrer itself
     * can carry a search query or a link from a private page, so it is
     * never stored.
     */
    private function source(string $referrer, string $ownHost): string
    {
        $host = parse_url($referrer, PHP_URL_HOST);

        if (! is_string($host)) {
            return 'direct';
        }

        if ($host === $ownHost) {
            return 'internal';
        }

        foreach (self::SearchEngines as $engine) {
            if ($host === $engine || str_ends_with($host, ".{$engine}")) {
                return 'search';
            }
        }

        return 'external';
    }
}
