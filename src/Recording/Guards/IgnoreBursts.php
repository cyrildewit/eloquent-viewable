<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Recording\Guards;

use CyrildeWit\EloquentViewable\Recording\Contracts\RecordingGuard;
use CyrildeWit\EloquentViewable\Recording\Data\ViewAttempt;
use CyrildeWit\EloquentViewable\Recording\Events\BurstDetected;
use CyrildeWit\EloquentViewable\Support\Config;
use CyrildeWit\EloquentViewable\Support\ViewableKey;
use CyrildeWit\EloquentViewable\Visitors\Fingerprint;
use CyrildeWit\EloquentViewable\Visitors\VisitorIdentity;
use Illuminate\Contracts\Cache\Factory as CacheFactory;
use Illuminate\Contracts\Cache\Repository as Cache;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Support\Carbon;

/**
 * Refuses a visitor that opens more than `recording.bursts.max` different
 * viewables within `recording.bursts.seconds`, and every view of theirs for
 * `recording.bursts.block_for` seconds after. Opening the same viewable again
 * does not count, that is the cooldown's job.
 *
 * Attempts count when they are asked about, not once they are recorded,
 * because a burst is about what the visitor does. The count only uses atomic
 * cache operations, so parallel requests from one bot cannot all slip under
 * the limit. It is the sliding-window estimate rate limiters use: the current
 * window plus the share of the previous window that still overlaps.
 */
final readonly class IgnoreBursts implements RecordingGuard
{
    private Cache $cache;

    public function __construct(
        private Config $config,
        private VisitorIdentity $identity,
        private Fingerprint $fingerprint,
        private Dispatcher $events,
        CacheFactory $cache,
    ) {
        $this->cache = $cache->store($config->burstCacheStore());
    }

    public function allows(ViewAttempt $attempt): bool
    {
        $keys = $this->keys($attempt);

        foreach ($keys as $key) {
            if ($this->cache->has("{$key}:blocked")) {
                return false;
            }
        }

        $viewableKey = ViewableKey::of($attempt->viewable);
        $viewable = hash('xxh128', "{$attempt->viewable->getMorphClass()}|{$viewableKey}");
        $allowed = true;

        foreach ($keys as $by => $key) {
            if ($this->count($key, $viewable) > $this->config->burstMax()) {
                $this->cache->put("{$key}:blocked", true, $this->config->burstBlockFor());
                $this->events->dispatch(new BurstDetected($attempt, $by));

                $allowed = false;
            }
        }

        return $allowed;
    }

    /** @return array<'visitor'|'network', string> */
    private function keys(ViewAttempt $attempt): array
    {
        $prefix = $this->config->burstKey();
        $keys = [];

        foreach ($this->config->burstKeys() as $by) {
            $id = $by === 'visitor'
                ? $this->identity->of($attempt->visitor, $attempt->viewer)
                : $this->fingerprint->of($attempt->visitor);

            if (in_array("{$prefix}:{$id}", $keys, true)) {
                continue;
            }

            $keys[$by] = "{$prefix}:{$id}";
        }

        return $keys;
    }

    /**
     * It returns the estimated number of different viewables within the
     * window, or zero when this viewable was already counted in it.
     */
    private function count(string $key, string $viewable): float
    {
        $window = $this->config->burstSeconds() * 1000;
        $now = Carbon::now()->getTimestampMs();
        $bucket = intdiv($now, $window);
        $ttl = $this->config->burstSeconds() * 2;

        if (! $this->cache->add("{$key}:{$bucket}:{$viewable}", true, $ttl)) {
            return 0;
        }

        $this->cache->add("{$key}:{$bucket}", 0, $ttl);

        $previousBucket = $bucket - 1;

        $current = (int) $this->cache->increment("{$key}:{$bucket}");
        $previous = $this->cache->get("{$key}:{$previousBucket}", 0);
        $overlap = 1 - (($now % $window) / $window);

        return $current + (is_numeric($previous) ? (int) $previous : 0) * $overlap;
    }
}
