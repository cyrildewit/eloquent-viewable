<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable;

use CyrildeWit\EloquentViewable\Contracts\CrawlerDetector;
use Jaybizzle\CrawlerDetect\CrawlerDetect;

class CrawlerDetectAdapter implements CrawlerDetector
{
    private CrawlerDetect $detector;

    public function __construct(CrawlerDetect $detector)
    {
        $this->detector = $detector;
    }

    /**
     * Determine if the current visitor is a crawler.
     */
    public function isCrawler(): bool
    {
        return $this->detector->isCrawler();
    }
}
