<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Recording\Guards;

use CyrildeWit\EloquentViewable\Crawlers\Contracts\CrawlerDetector;
use CyrildeWit\EloquentViewable\Recording\Contracts\RecordingGuard;
use CyrildeWit\EloquentViewable\Recording\Data\ViewAttempt;

final readonly class IgnoreCrawlers implements RecordingGuard
{
    public function __construct(private CrawlerDetector $detector) {}

    public function allows(ViewAttempt $attempt): bool
    {
        return ! $this->detector->isCrawler($attempt->visitor->userAgent());
    }
}
