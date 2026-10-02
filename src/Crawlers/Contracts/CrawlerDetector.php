<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Crawlers\Contracts;

interface CrawlerDetector
{
    /**
     * Whether the user agent belongs to a crawler. A null or empty user agent
     * is never a crawler: there is nothing to judge, and an API client that
     * sends none is a legitimate caller.
     */
    public function isCrawler(?string $userAgent): bool;
}
