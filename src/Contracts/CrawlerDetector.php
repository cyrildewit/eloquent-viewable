<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Contracts;

interface CrawlerDetector
{
    /**
     * Determine if the current user is a crawler.
     *
     * @return bool
     */
    public function isCrawler(): bool;
}
