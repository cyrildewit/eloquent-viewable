<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Doctor\Checks;

use Carbon\CarbonImmutable;
use CyrildeWit\EloquentViewable\Doctor\Contracts\Check;
use CyrildeWit\EloquentViewable\Doctor\Data\Finding;
use CyrildeWit\EloquentViewable\Exceptions\InvalidConfiguration;
use CyrildeWit\EloquentViewable\Recording\Data\StreamBacklog;
use CyrildeWit\EloquentViewable\Recording\Stores\RedisStreamStore;
use CyrildeWit\EloquentViewable\Recording\Stores\StoreManager;
use CyrildeWit\EloquentViewable\Support\Config;
use Generator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class RedisStreamCheck implements Check
{
    /**
     * How long, in seconds, the oldest buffered view may wait before the
     * flush is taken to have stopped. A flush every minute stays well under.
     */
    public const int StaleAfter = 300;

    public function __construct(
        protected Config $config,
        protected StoreManager $stores,
    ) {}

    public function name(): string
    {
        return 'Redis stream';
    }

    /**
     * @return Generator<int, Finding>
     *
     * @throws InvalidConfiguration
     */
    public function run(): Generator
    {
        if ($this->config->storeDriver() !== 'redis') {
            yield Finding::skipped('The `redis` store driver is not in use.');

            return;
        }

        $store = $this->stores->driver('redis');

        if (! $store instanceof RedisStreamStore) {
            yield Finding::skipped('The `redis` store driver is one of your own.');

            return;
        }

        $backlog = $store->backlog();

        yield $this->waiting($backlog);

        if ($backlog->stalled > 0) {
            $views = Str::plural('view', $backlog->stalled);

            yield Finding::warning(
                "{$backlog->stalled} {$views} were taken by a flush that never acknowledged them, so it failed halfway through.",
                'Check the output and the logs of `views:flush`. The next run takes them again.',
            );
        }
    }

    protected function waiting(StreamBacklog $backlog): Finding
    {
        if ($backlog->length === 0) {
            return Finding::pass('The stream is empty: every buffered view has landed in the views table.');
        }

        $views = $backlog->length === 1
            ? 'view waits'
            : 'views wait';

        if (! $backlog->oldestAt instanceof CarbonImmutable) {
            return Finding::pass("{$backlog->length} {$views} in the stream.");
        }

        $since = $backlog->oldestAt->diffForHumans(Carbon::now(), syntax: Carbon::DIFF_ABSOLUTE);

        if ($backlog->oldestAt->lt(Carbon::now()->subSeconds(self::StaleAfter))) {
            return Finding::warning(
                "{$backlog->length} {$views} in the stream, the oldest for {$since}, so `views:flush` does not run or does not keep up.",
                'Schedule `views:flush` every minute, and raise its `--batch` if it runs but falls behind.',
            );
        }

        return Finding::pass("{$backlog->length} {$views} in the stream, the oldest for {$since}.");
    }
}
