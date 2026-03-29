<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable;

use CyrildeWit\EloquentViewable\Contracts\CrawlerDetector;
use Jaybizzle\CrawlerDetect\CrawlerDetect;

class CrawlerDetectAdapter implements CrawlerDetector
{
    public function __construct(private readonly CrawlerDetect $detector) {}

    /**
     * Determine if the current visitor is a crawler.
     */
    public function isCrawler(): bool
    {
        return $this->detector->isCrawler();
    }
}
