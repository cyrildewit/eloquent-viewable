<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Crawlers\Detectors;

use CyrildeWit\EloquentViewable\Crawlers\Contracts\CrawlerDetector;
use Jaybizzle\CrawlerDetect\CrawlerDetect;

/**
 * The verdict on the last user agent is kept, because the `IgnoreCrawlers`
 * guard and the `Device` dimension both ask about the same view, and the
 * crawler pattern is the costly part of either.
 */
final class CrawlerDetectAdapter implements CrawlerDetector
{
    private ?string $lastUserAgent = null;

    private bool $lastVerdict = false;

    public function __construct(private readonly CrawlerDetect $detector) {}

    public function isCrawler(?string $userAgent): bool
    {
        // CrawlerDetect falls back to the user agent it captured at
        // construction when the argument is empty, and construction without
        // arguments reads $_SERVER. Deciding the empty case here keeps the
        // adapter free of request state.
        if ($userAgent === null) {
            return false;
        }

        if (trim($userAgent) === '') {
            return false;
        }

        if ($userAgent === $this->lastUserAgent) {
            return $this->lastVerdict;
        }

        $this->lastVerdict = $this->detector->isCrawler($userAgent);
        $this->lastUserAgent = $userAgent;

        return $this->lastVerdict;
    }
}
