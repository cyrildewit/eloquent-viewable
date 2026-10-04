<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Recording\Guards;

use CyrildeWit\EloquentViewable\Recording\Contracts\RecordingGuard;
use CyrildeWit\EloquentViewable\Recording\Contracts\RemembersRecordedViews;
use CyrildeWit\EloquentViewable\Recording\Data\ViewAttempt;
use CyrildeWit\EloquentViewable\Support\Config;
use CyrildeWit\EloquentViewable\Visitors\VisitorIdentity;
use Illuminate\Cache\RateLimiter;
use Illuminate\Contracts\Cache\Factory as CacheFactory;

/**
 * Caps the views one visitor records per minute across every viewable, so a
 * scraper walking through thousands of pages does not skew the rankings.
 * Only views that pass every guard count towards the limit.
 */
final readonly class ThrottleVisitors implements RecordingGuard, RemembersRecordedViews
{
    private RateLimiter $limiter;

    public function __construct(
        private Config $config,
        private VisitorIdentity $identity,
        CacheFactory $cache,
    ) {
        $this->limiter = new RateLimiter($cache->store($config->throttleCacheStore()));
    }

    public function allows(ViewAttempt $attempt): bool
    {
        return ! $this->limiter->tooManyAttempts($this->key($attempt), $this->config->throttleMaxPerMinute());
    }

    public function remember(ViewAttempt $attempt): void
    {
        $this->limiter->hit($this->key($attempt), 60);
    }

    private function key(ViewAttempt $attempt): string
    {
        $visitor = $this->identity->of($attempt->visitor, $attempt->viewer);

        return "{$this->config->throttleKey()}:{$visitor}";
    }
}
