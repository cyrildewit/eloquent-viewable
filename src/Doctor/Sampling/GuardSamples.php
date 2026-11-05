<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Doctor\Sampling;

use CyrildeWit\EloquentViewable\Doctor\Data\GuardSample;
use CyrildeWit\EloquentViewable\Exceptions\InvalidConfiguration;
use CyrildeWit\EloquentViewable\Recording\Contracts\RecordingGuard;
use CyrildeWit\EloquentViewable\Support\Config;
use Illuminate\Contracts\Cache\Factory as CacheFactory;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Support\Carbon;
use Throwable;

/**
 * Counts per day how many attempts were recorded and how many each guard
 * refused, so `views:doctor` can tell when a guard refuses an unusual share.
 * A counter expires a day after the last day the doctor reads, so nothing
 * has to clean them up.
 */
class GuardSamples
{
    public const int Days = 7;

    public const string Recorded = 'recorded';

    public function __construct(
        protected Config $config,
        protected CacheFactory $cache,
    ) {}

    public function countRecorded(): void
    {
        $this->increment(self::Recorded);
    }

    public function countRefused(RecordingGuard $guard): void
    {
        $this->increment($guard::class);
    }

    /**
     * @param  list<string>  $guards
     *
     * @throws InvalidConfiguration
     */
    public function lastDays(array $guards, int $days = self::Days): GuardSample
    {
        $dates = [];

        for ($daysAgo = 0; $daysAgo < $days; $daysAgo++) {
            $dates[] = Carbon::now()->subDays($daysAgo)->toDateString();
        }

        $refused = [];

        foreach ($guards as $guard) {
            $refused[$guard] = $this->sum($dates, $guard);
        }

        return new GuardSample($days, $this->sum($dates, self::Recorded), $refused);
    }

    /**
     * A sample is not worth a view, so a cache that cannot be reached leaves
     * the attempt alone rather than failing it.
     */
    protected function increment(string $outcome): void
    {
        try {
            $key = $this->key(Carbon::now()->toDateString(), $outcome);
            $store = $this->store();

            $store->add($key, 0, Carbon::now()->addDays(self::Days + 1));
            $store->increment($key);
        } catch (Throwable) {
            return;
        }
    }

    /**
     * @param  list<string>  $dates
     *
     * @throws InvalidConfiguration
     */
    protected function sum(array $dates, string $outcome): int
    {
        $sum = 0;

        foreach ($dates as $date) {
            $value = $this->store()->get($this->key($date, $outcome));

            $sum += is_numeric($value) ? (int) $value : 0;
        }

        return $sum;
    }

    /** @throws InvalidConfiguration */
    protected function key(string $date, string $outcome): string
    {
        return "{$this->config->sampleKey()}:{$date}:{$outcome}";
    }

    /** @throws InvalidConfiguration */
    protected function store(): Repository
    {
        return $this->cache->store($this->config->sampleCacheStore());
    }
}
