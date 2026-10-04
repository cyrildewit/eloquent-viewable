<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Samples\PrivacyFirstAnalytics;

use Carbon\Carbon;
use Carbon\CarbonInterface;
use CyrildeWit\EloquentViewable\Recording\Events\ViewSkipped;
use Illuminate\Contracts\Cache\Repository as Cache;
use Illuminate\Contracts\Config\Repository as Config;

/**
 * Tallies the views the guards refused, per guard and per day, so the docs
 * team can tell a quiet page from one whose readers opt out.
 */
final readonly class SkippedViews
{
    private const string CacheKey = 'samples.skipped-views';

    private const int KeepDays = 7;

    public function __construct(
        private Cache $cache,
        private Config $config,
    ) {}

    public function handle(ViewSkipped $event): void
    {
        $key = $this->key(Carbon::today(), class_basename($event->guard));

        // `add()` only writes when the key is missing, so the expiry is set
        // once and the increment that follows stays atomic.
        $this->cache->add($key, 0, Carbon::today()->addDays(self::KeepDays));
        $this->cache->increment($key);
    }

    /**
     * @return array<string, int> the skipped views of the day, keyed by guard
     */
    public function on(CarbonInterface $day): array
    {
        $tally = [];

        /** @var list<class-string> $guards */
        $guards = $this->config->get('eloquent-viewable.recording.guards', []);

        foreach ($guards as $guard) {
            $count = (int) $this->cache->get($this->key($day, class_basename($guard)), 0);

            if ($count > 0) {
                $tally[class_basename($guard)] = $count;
            }
        }

        return $tally;
    }

    private function key(CarbonInterface $day, string $guard): string
    {
        return self::CacheKey.":{$day->toDateString()}:{$guard}";
    }
}
