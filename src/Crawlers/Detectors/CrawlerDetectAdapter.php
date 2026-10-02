<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Crawlers\Detectors;

use CyrildeWit\EloquentViewable\Crawlers\Contracts\CrawlerDetector;
use Jaybizzle\CrawlerDetect\CrawlerDetect;

final readonly class CrawlerDetectAdapter implements CrawlerDetector
{
    public function __construct(private CrawlerDetect $detector) {}

    public function isCrawler(?string $userAgent): bool
    {
        // CrawlerDetect falls back to the user agent it captured at
        // construction when the argument is empty, and construction without
        // arguments reads $_SERVER. Deciding the empty case here keeps the
        // adapter free of request state.
        if ($userAgent === null || trim($userAgent) === '') {
            return false;
        }

        return $this->detector->isCrawler($userAgent);
    }
}
