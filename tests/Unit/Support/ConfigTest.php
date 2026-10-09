<?php

declare(strict_types=1);

use CyrildeWit\EloquentViewable\Dimensions\Countries\HeaderCountry;
use CyrildeWit\EloquentViewable\Dimensions\Country;
use CyrildeWit\EloquentViewable\Dimensions\Source;
use CyrildeWit\EloquentViewable\Exceptions\InvalidConfiguration;
use CyrildeWit\EloquentViewable\Models\View;
use CyrildeWit\EloquentViewable\Recording\Guards\EnforceCooldown;
use CyrildeWit\EloquentViewable\Recording\Guards\IgnoreCrawlers;
use CyrildeWit\EloquentViewable\Support\Config;
use CyrildeWit\EloquentViewable\Support\DimensionFilter;
use CyrildeWit\EloquentViewable\Support\Duration;
use CyrildeWit\EloquentViewable\Support\Granularity;
use CyrildeWit\EloquentViewable\Tests\Fixtures\Models\Post;
use CyrildeWit\EloquentViewable\Tests\Fixtures\Models\SoftDeletableView;
use Illuminate\Config\Repository;

/** @param  array<string, mixed>  $values */
function packageConfig(array $values = []): Config
{
    return new Config(new Repository(['eloquent-viewable' => $values]));
}

it('reads the view model settings', function (): void {
    expect(packageConfig(['models' => ['view' => ['table_name' => 'page_views', 'connection' => 'analytics']]]))
        ->viewTable()->toBe('page_views')
        ->viewConnection()->toBe('analytics')
        ->and(packageConfig())
        ->viewTable()->toBeNull()
        ->viewConnection()->toBeNull();
});

it('reads the cache settings', function (): void {
    expect(packageConfig(['querying' => ['cache' => ['key' => 'views', 'store' => 'redis']]]))
        ->cacheKey()->toBe('views')
        ->cacheStore()->toBe('redis')
        ->and(packageConfig())->cacheStore()->toBeNull();
});

it('reads the store driver', function (): void {
    expect(packageConfig(['recording' => ['store' => ['driver' => 'null']]]))->storeDriver()->toBe('null');
});

it('reads the source driver', function (): void {
    expect(packageConfig(['querying' => ['source' => ['driver' => 'aggregate']]]))->sourceDriver()->toBe('aggregate');
});

it('reads the guard classes', function (): void {
    expect(packageConfig(['recording' => ['guards' => [IgnoreCrawlers::class, EnforceCooldown::class]]]))->guards()->toBe([IgnoreCrawlers::class, EnforceCooldown::class])
        ->and(packageConfig(['recording' => ['guards' => []]]))->guards()->toBe([]);
});

it('rejects guards that are not a list of classes', function (mixed $value, string $described): void {
    expect(fn (): array => packageConfig(['recording' => ['guards' => $value]])->guards())
        ->toThrow(InvalidConfiguration::class, "The `eloquent-viewable.recording.guards` config value must be a list of class names, {$described} given.");
})->with([
    'null' => [null, 'null'],
    'string' => [IgnoreCrawlers::class, '`"'.addslashes(IgnoreCrawlers::class).'"`'],
    'missing class' => [['App\\Guards\\Missing'], '`"App\\\\Guards\\\\Missing"`'],
    'integer' => [[1], '`1`'],
]);

it('reads the interval cap', function (): void {
    expect(packageConfig(['querying' => ['max_intervals' => '500']]))->maxIntervals()->toBe(500);
});

it('reads the queue settings', function (): void {
    expect(packageConfig(['recording' => ['queue' => ['enabled' => true, 'connection' => 'sqs', 'queue' => 'views']]]))
        ->queueEnabled()->toBeTrue()
        ->queueConnection()->toBe('sqs')
        ->queueName()->toBe('views')
        ->and(packageConfig())
        ->queueEnabled()->toBeFalse()
        ->queueConnection()->toBeNull()
        ->queueName()->toBeNull();
});

it('reads the viewer settings', function (): void {
    expect(packageConfig(['recording' => ['viewer' => ['enabled' => true, 'guard' => 'api']]]))
        ->viewerEnabled()->toBeTrue()
        ->viewerGuard()->toBe('api')
        ->and(packageConfig())
        ->viewerEnabled()->toBeFalse()
        ->viewerGuard()->toBeNull();
});

it('reads the ignored ip addresses', function (): void {
    expect(packageConfig(['recording' => ['ignored_ip_addresses' => ['127.0.0.1', '10.0.0.1']]]))
        ->ignoredIpAddresses()->toBe(['127.0.0.1', '10.0.0.1'])
        ->and(packageConfig())->ignoredIpAddresses()->toBe([]);
});

it('reads a single ignored ip address', function (): void {
    expect(packageConfig(['recording' => ['ignored_ip_addresses' => '127.0.0.1']]))
        ->ignoredIpAddresses()->toBe(['127.0.0.1']);
});

it('rejects ignored ip addresses that are not strings', function (): void {
    expect(fn (): array => packageConfig(['recording' => ['ignored_ip_addresses' => ['127.0.0.1', 1]]])->ignoredIpAddresses())
        ->toThrow(InvalidConfiguration::class, 'The `eloquent-viewable.recording.ignored_ip_addresses` config value must be a list of strings, `1` given.');
});

it('reads the throttle settings', function (): void {
    expect(packageConfig(['recording' => ['throttle' => ['max_per_minute' => '30', 'key' => 'throttle', 'store' => 'redis']]]))
        ->throttleMaxPerMinute()->toBe(30)
        ->throttleKey()->toBe('throttle')
        ->throttleCacheStore()->toBe('redis')
        ->and(packageConfig())->throttleCacheStore()->toBeNull();
});

it('reads the burst settings', function (): void {
    expect(packageConfig(['recording' => ['bursts' => ['max' => '4', 'seconds' => '3', 'block_for' => '60', 'by' => ['network'], 'key' => 'bursts', 'store' => 'redis']]]))
        ->burstMax()->toBe(4)
        ->burstSeconds()->toBe(3)
        ->burstBlockFor()->toBe(60)
        ->burstKeys()->toBe(['network'])
        ->burstKey()->toBe('bursts')
        ->burstCacheStore()->toBe('redis')
        ->and(packageConfig())->burstKeys()->toBe(['visitor', 'network'])
        ->and(packageConfig())->burstCacheStore()->toBeNull();
});

it('rejects burst keys that are not visitor or network', function (mixed $value, string $described): void {
    expect(fn (): array => packageConfig(['recording' => ['bursts' => ['by' => $value]]])->burstKeys())
        ->toThrow(InvalidConfiguration::class, "The `eloquent-viewable.recording.bursts.by` config value must be a list of `visitor`, `network`, {$described} given.");
})->with([
    'string' => ['visitor', '`"visitor"`'],
    'empty' => [[], 'array'],
    'unknown key' => [['visitor', 'ip'], '`"ip"`'],
]);

it('reads the visitor cookie settings', function (): void {
    expect(packageConfig(['visitor' => ['cookie' => ['name' => 'who', 'lifetime' => '120']]]))
        ->visitorCookieName()->toBe('who')
        ->visitorCookieLifetime()->toBe(120);
});

it('reads the visitor identity', function (): void {
    expect(packageConfig(['visitor' => ['identity' => 'viewer']]))->visitorIdentity()->toBe('viewer')
        ->and(packageConfig(['visitor' => ['identity' => 'fingerprint']]))->visitorIdentity()->toBe('fingerprint')
        ->and(packageConfig())->visitorIdentity()->toBe('cookie');
});

it('reads the fingerprint settings', function (): void {
    expect(packageConfig(['visitor' => ['fingerprint' => ['store' => 'redis', 'key' => 'salt']]]))
        ->fingerprintCacheStore()->toBe('redis')
        ->fingerprintKey()->toBe('salt')
        ->and(packageConfig())->fingerprintCacheStore()->toBeNull();
});

it('reads the fingerprint rotation', function (): void {
    expect(packageConfig(['visitor' => ['fingerprint' => ['rotation' => 'week']]]))->fingerprintRotation()->toBe('week')
        ->and(packageConfig(['visitor' => ['fingerprint' => ['rotation' => 'month']]]))->fingerprintRotation()->toBe('month')
        ->and(packageConfig())->fingerprintRotation()->toBe('day');
});

it('rejects an unknown fingerprint rotation', function (): void {
    expect(fn (): string => packageConfig(['visitor' => ['fingerprint' => ['rotation' => 'year']]])->fingerprintRotation())
        ->toThrow(InvalidConfiguration::class, 'The `eloquent-viewable.visitor.fingerprint.rotation` config value must be one of `day`, `week`, `month`, `"year"` given.');
});

it('rejects an unknown visitor identity', function (mixed $value, string $described): void {
    expect(fn (): string => packageConfig(['visitor' => ['identity' => $value]])->visitorIdentity())
        ->toThrow(InvalidConfiguration::class, "The `eloquent-viewable.visitor.identity` config value must be one of `cookie`, `viewer`, `fingerprint`, {$described} given.");
})->with([
    'word' => ['session', '`"session"`'],
    'null' => [null, 'null'],
    'integer' => [1, '`1`'],
]);

it('reads the cooldown settings', function (): void {
    expect(packageConfig(['cooldown' => ['store' => 'cache', 'key' => 'cooldowns', 'cache' => ['store' => 'redis']]]))
        ->cooldownStore()->toBe('cache')
        ->cooldownKey()->toBe('cooldowns')
        ->cooldownCacheStore()->toBe('redis')
        ->and(packageConfig())->cooldownCacheStore()->toBeNull();
});

it('rejects a positive integer key that is not one', function (string $method, string $key, mixed $value, string $described): void {
    $values = [];
    data_set($values, $key, $value);

    expect(fn (): int => packageConfig($values)->{$method}())
        ->toThrow(InvalidConfiguration::class, "The `eloquent-viewable.{$key}` config value must be a positive integer, {$described} given.");
})->with([
    'interval cap' => ['maxIntervals', 'querying.max_intervals'],
    'cookie lifetime' => ['visitorCookieLifetime', 'visitor.cookie.lifetime'],
    'throttle maximum' => ['throttleMaxPerMinute', 'recording.throttle.max_per_minute'],
    'burst maximum' => ['burstMax', 'recording.bursts.max'],
    'burst seconds' => ['burstSeconds', 'recording.bursts.seconds'],
    'burst block' => ['burstBlockFor', 'recording.bursts.block_for'],
    'presence window' => ['presenceWindow', 'presence.window'],
    'presence heartbeat' => ['presenceHeartbeat', 'presence.heartbeat'],
    'presence candidates' => ['presenceMaxCandidates', 'presence.max_candidates'],
])->with([
    'zero' => [0, '`0`'],
    'negative' => [-1, '`-1`'],
    'float' => [1.5, '`1.5`'],
    'word' => ['many', '`"many"`'],
    'null' => [null, 'null'],
    'array' => [[], 'array'],
]);

it('rejects an optional string key that is not a string', function (string $method, string $key): void {
    $values = [];
    data_set($values, $key, 1);

    expect(fn (): ?string => packageConfig($values)->{$method}())
        ->toThrow(InvalidConfiguration::class, "The `eloquent-viewable.{$key}` config value must be a string or null, `1` given.");
})->with([
    'view table' => ['viewTable', 'models.view.table_name'],
    'view connection' => ['viewConnection', 'models.view.connection'],
    'queue connection' => ['queueConnection', 'recording.queue.connection'],
    'queue name' => ['queueName', 'recording.queue.queue'],
    'viewer guard' => ['viewerGuard', 'recording.viewer.guard'],
    'cache store' => ['cacheStore', 'querying.cache.store'],
    'cooldown cache store' => ['cooldownCacheStore', 'cooldown.cache.store'],
    'fingerprint cache store' => ['fingerprintCacheStore', 'visitor.fingerprint.store'],
    'throttle cache store' => ['throttleCacheStore', 'recording.throttle.store'],
    'burst cache store' => ['burstCacheStore', 'recording.bursts.store'],
    'presence redis connection' => ['presenceRedisConnection', 'presence.redis.connection'],
]);

it('rejects an empty key', function (string $method, string $key): void {
    $empty = [];
    data_set($empty, $key, '');

    expect(fn (): string => packageConfig()->{$method}())
        ->toThrow(InvalidConfiguration::class, "The `eloquent-viewable.{$key}` config value must be a non-empty string, null given.")
        ->and(fn (): string => packageConfig($empty)->{$method}())
        ->toThrow(InvalidConfiguration::class, "The `eloquent-viewable.{$key}` config value must be a non-empty string, `\"\"` given.");
})->with([
    'cache key' => ['cacheKey', 'querying.cache.key'],
    'cooldown store' => ['cooldownStore', 'cooldown.store'],
    'cooldown key' => ['cooldownKey', 'cooldown.key'],
    'throttle key' => ['throttleKey', 'recording.throttle.key'],
    'burst key' => ['burstKey', 'recording.bursts.key'],
    'visitor cookie name' => ['visitorCookieName', 'visitor.cookie.name'],
    'fingerprint key' => ['fingerprintKey', 'visitor.fingerprint.key'],
    'store driver' => ['storeDriver', 'recording.store.driver'],
    'source driver' => ['sourceDriver', 'querying.source.driver'],
    'presence driver' => ['presenceDriver', 'presence.driver'],
    'presence redis prefix' => ['presenceRedisPrefix', 'presence.redis.prefix'],
]);

it('reads the view model class', function (): void {
    expect(packageConfig())->viewModel()->toBe(View::class)
        ->and(packageConfig(['models' => ['view' => ['class' => SoftDeletableView::class]]]))->viewModel()->toBe(SoftDeletableView::class);
});

it('rejects a view model that does not extend the shipped one', function (mixed $value, string $described): void {
    expect(fn (): string => packageConfig(['models' => ['view' => ['class' => $value]]])->viewModel())
        ->toThrow(InvalidConfiguration::class, 'The `eloquent-viewable.models.view.class` config value must be the name of a class that extends `'.View::class."`, {$described} given.");
})->with([
    'unrelated model' => [Post::class, '`"'.addslashes(Post::class).'"`'],
    'missing class' => ['App\Models\Missing', '`"App\\\\Models\\\\Missing"`'],
    'null' => [null, 'null'],
    'array' => [[], 'array'],
]);

it('reads the redis store settings', function (): void {
    $config = packageConfig(['recording' => ['store' => ['redis' => [
        'connection' => 'views',
        'stream' => 'views:stream',
        'group' => 'flushers',
        'landing' => 'array',
    ]]]]);

    expect($config)
        ->redisConnection()->toBe('views')
        ->redisStream()->toBe('views:stream')
        ->redisGroup()->toBe('flushers')
        ->redisLandingDriver()->toBe('array')
        ->and(packageConfig())->redisConnection()->toBeNull();
});

it('rejects an empty redis store key', function (string $method, string $key): void {
    expect(fn (): string => packageConfig()->{$method}())
        ->toThrow(InvalidConfiguration::class, "The `eloquent-viewable.recording.store.redis.{$key}` config value must be a non-empty string, null given.");
})->with([
    ['redisStream', 'stream'],
    ['redisGroup', 'group'],
    ['redisLandingDriver', 'landing'],
]);

it('rejects a redis connection that is not a string', function (): void {
    expect(fn (): ?string => packageConfig(['recording' => ['store' => ['redis' => ['connection' => 1]]]])->redisConnection())
        ->toThrow(InvalidConfiguration::class, 'The `eloquent-viewable.recording.store.redis.connection` config value must be a string or null, `1` given.');
});

it('reads the retention settings', function (): void {
    $config = packageConfig(['retention' => [
        'anonymise' => ['after' => '30d', 'columns' => ['viewer']],
        'prune' => ['after' => '2y'],
        'chunk' => '250',
    ]]);

    expect($config->anonymiseAfter()?->shorthand())->toBe('30d')
        ->and($config->anonymiseColumns())->toBe(['viewer'])
        ->and($config->pruneAfter()?->shorthand())->toBe('2y')
        ->and($config->retentionChunk())->toBe(250)
        ->and(packageConfig())
        ->anonymiseAfter()->toBeNull()
        ->pruneAfter()->toBeNull()
        ->anonymiseColumns()->toBe(['visitor', 'viewer', 'context', 'dimensions']);
});

it('rejects a retention duration that is not a shorthand', function (mixed $value, string $described): void {
    expect(fn (): mixed => packageConfig(['retention' => ['prune' => ['after' => $value]]])->pruneAfter())
        ->toThrow(InvalidConfiguration::class, "The `eloquent-viewable.retention.prune.after` config value must be a duration such as `30d` or `2y`, or null, {$described} given.");
})->with([
    'unknown unit' => ['90x', '`"90x"`'],
    'zero' => ['0d', '`"0d"`'],
    'integer' => [90, '`90`'],
]);

it('rejects anonymised columns that are not visitor, viewer, context or dimensions', function (mixed $value, string $described): void {
    expect(fn (): array => packageConfig(['retention' => ['anonymise' => ['columns' => $value]]])->anonymiseColumns())
        ->toThrow(InvalidConfiguration::class, "The `eloquent-viewable.retention.anonymise.columns` config value must be a list of `visitor`, `viewer`, `context`, `dimensions`, {$described} given.");
})->with([
    'string' => ['visitor', '`"visitor"`'],
    'empty' => [[], 'array'],
    'unknown column' => [['visitor', 'collection'], '`"collection"`'],
    'nested' => [[['visitor']], 'array'],
]);

it('reads the rollup settings', function (): void {
    $config = packageConfig(['retention' => ['rollups' => [
        'table' => 'rollups',
        'timezone' => 'Europe/Amsterdam',
        'settle' => '2h',
        'tiers' => ['day' => '2y', 'month' => null],
        'groupings' => ['type', 'viewable'],
        'strict' => true,
    ]]]);

    expect($config->rollupTable())->toBe('rollups')
        ->and($config->rollupTimezone())->toBe('Europe/Amsterdam')
        ->and($config->rollupSettle()?->shorthand())->toBe('2h')
        ->and(array_map(fn (?Duration $keep): ?string => $keep?->shorthand(), $config->rollupTiers()))->toBe(['day' => '2y', 'month' => null])
        ->and($config->rollupGroupings())->toBe(['viewable', 'type'])
        ->and($config->rollupsStrict())->toBeTrue()
        ->and(packageConfig())
        ->rollupTiers()->toBe([])
        ->rollupGroupings()->toBe(['viewable', 'viewable_collection', 'type'])
        ->rollupsStrict()->toBeFalse();
});

it('rejects rollup tiers that are not a map of tiers to durations', function (mixed $value, string $message): void {
    expect(fn (): array => packageConfig(['retention' => ['rollups' => ['tiers' => $value]]])->rollupTiers())
        ->toThrow(InvalidConfiguration::class, $message);
})->with([
    'string' => ['day', 'must map `hour`, `day`, `month` or `year` to a duration or null, `"day"` given.'],
    'week' => [['week' => null], 'must map `hour`, `day`, `month` or `year` to a duration or null, `"week"` given.'],
    'list' => [['day'], 'must map `hour`, `day`, `month` or `year` to a duration or null, `0` given.'],
    'bad duration' => [['day' => 'forever'], 'The `eloquent-viewable.retention.rollups.tiers.day` config value must be a duration'],
]);

it('rejects groupings that are not known', function (mixed $value): void {
    expect(fn (): array => packageConfig(['retention' => ['rollups' => ['groupings' => $value]]])->rollupGroupings())
        ->toThrow(InvalidConfiguration::class, 'The `eloquent-viewable.retention.rollups.groupings` config value must be a list of `viewable`, `viewable_collection`, `type`, `type_collection`');
})->with([
    'string' => ['type'],
    'empty' => [[]],
    'unknown' => [['viewer']],
]);

it('reads the counter columns', function (): void {
    $counters = packageConfig(['querying' => ['counters' => [Post::class => [
        'views_count',
        'weekly' => ['period' => '7d', 'unique' => true, 'collection' => 'featured'],
    ]]]])->counters();

    expect(array_keys($counters))->toBe([Post::class])
        ->and(array_keys($counters[Post::class]))->toBe(['views_count', 'weekly'])
        ->and($counters[Post::class]['views_count'])
        ->period->toBeNull()
        ->unique->toBeFalse()
        ->and($counters[Post::class]['weekly'])
        ->unique->toBeTrue()
        ->collection->toBe('featured')
        ->and($counters[Post::class]['weekly']->period?->getRouteKey())->toBe('7d')
        ->and(packageConfig()->counters())->toBeEmpty();
});

it('reads the dimensions of a counter column by name', function (): void {
    $query = packageConfig(['querying' => ['counters' => [Post::class => [
        'google_views' => ['dimensions' => ['source' => 'Google', 'device' => ['mobile', 'tablet']]],
    ]]]])->counters()[Post::class]['google_views'];

    expect($query->dimensions)->toEqual([
        new DimensionFilter('source', 'source', ['Google']),
        new DimensionFilter('device', 'device', ['mobile', 'tablet']),
    ]);
});

it('rejects counters that are not viewable models mapped to columns', function (mixed $value): void {
    expect(fn (): array => packageConfig(['querying' => ['counters' => $value]])->counters())
        ->toThrow(InvalidConfiguration::class, 'The `eloquent-viewable.querying.counters` config value must map viewable model classes to their counter columns');
})->with([
    'string' => ['views_count'],
    'list' => [[['views_count']]],
    'not a model' => [[Config::class => ['views_count']]],
    'not a viewable' => [[SoftDeletableView::class => ['views_count']]],
    'no columns' => [[Post::class => []]],
    'a column by number' => [[Post::class => [1]]],
    'options not a map' => [[Post::class => ['views_count' => true]]],
    'unknown option' => [[Post::class => ['views_count' => ['viewer' => 1]]]],
    'period not a string' => [[Post::class => ['views_count' => ['period' => 7]]]],
    'collection not a string' => [[Post::class => ['views_count' => ['collection' => 1]]]],
    'dimensions not a map' => [[Post::class => ['views_count' => ['dimensions' => 'source']]]],
    'a dimension without a name' => [[Post::class => ['views_count' => ['dimensions' => ['Google']]]]],
    'a dimension value not a string' => [[Post::class => ['views_count' => ['dimensions' => ['source' => 1]]]]],
    'dimension values not a list' => [[Post::class => ['views_count' => ['dimensions' => ['source' => ['a' => 'Google']]]]]],
    'a dimension value in a list not a string' => [[Post::class => ['views_count' => ['dimensions' => ['source' => ['Google', 1]]]]]],
]);

describe('hot scores', function (): void {
    it('reads the hot option of a counter column', function (): void {
        $config = packageConfig(['querying' => ['counters' => [Post::class => [
            'views_count',
            'hot' => ['hot' => true],
            'fresh' => ['hot' => 'published_at', 'unique' => true],
            'slow' => ['hot' => ['from' => 'published_at', 'every' => '1d']],
        ]]]]);

        $scores = $config->hotScores()[Post::class];

        expect(array_keys($scores))->toBe(['hot', 'fresh', 'slow'])
            ->and($scores['hot']->signature())->toBe('created_at:12h')
            ->and($scores['fresh']->signature())->toBe('published_at:12h')
            ->and($scores['slow']->signature())->toBe('published_at:1d')
            ->and(array_keys($config->counters()[Post::class]))->toBe(['views_count', 'hot', 'fresh', 'slow'])
            ->and(packageConfig()->hotScores())->toBeEmpty();
    });

    it('rejects a hot option it cannot use', function (mixed $hot): void {
        $config = packageConfig(['querying' => ['counters' => [Post::class => ['hot' => ['hot' => $hot]]]]]);

        expect(fn (): array => $config->hotScores())
            ->toThrow(InvalidConfiguration::class, 'The `hot` option of the `hot` counter column in `eloquent-viewable.querying.counters` must be');
    })->with([
        'false' => [false],
        'an integer' => [1],
        'an empty column' => [''],
        'unknown option' => [['decay' => '1d']],
        'from not a string' => [['from' => 1]],
        'every not a duration' => [['every' => 'soon']],
        'every not a string' => [['every' => 12]],
    ]);

    it('rejects a milestone on a hot score', function (): void {
        $config = packageConfig([
            'querying' => ['counters' => [Post::class => ['hot' => ['hot' => true]]]],
            'milestones' => ['thresholds' => [Post::class => ['hot' => [100]]]],
        ]);

        expect(fn (): array => $config->milestones())
            ->toThrow(InvalidConfiguration::class, 'which holds a hot score rather than a count');
    });
});

describe('milestones', function (): void {
    it('reads the thresholds of counter columns without a period', function (): void {
        $config = packageConfig([
            'querying' => ['counters' => [Post::class => ['views_count', 'unique' => ['unique' => true, 'collection' => 'featured']]]],
            'milestones' => ['table' => 'post_milestones', 'thresholds' => [Post::class => [
                'views_count' => [100, 1_000],
                'unique' => [10],
            ]]],
        ]);

        expect($config->milestones())->toBe([Post::class => ['views_count' => [100, 1_000], 'unique' => [10]]])
            ->and($config->milestonesTable())->toBe('post_milestones')
            ->and(packageConfig()->milestones())->toBeEmpty();
    });

    it('rejects thresholds that are not ascending lists of integers', function (mixed $value): void {
        $config = packageConfig([
            'querying' => ['counters' => [Post::class => ['views_count']]],
            'milestones' => ['thresholds' => $value],
        ]);

        expect(fn (): array => $config->milestones())
            ->toThrow(InvalidConfiguration::class, 'The `eloquent-viewable.milestones.thresholds` config value must map viewable model classes to their counter columns, each with a list of thresholds in ascending order');
    })->with([
        'string' => ['views_count'],
        'a class by number' => [[[100]]],
        'columns not a map' => [[Post::class => 'views_count']],
        'no columns' => [[Post::class => []]],
        'thresholds not a list' => [[Post::class => ['views_count' => 100]]],
        'thresholds keyed' => [[Post::class => ['views_count' => ['first' => 100]]]],
        'no thresholds' => [[Post::class => ['views_count' => []]]],
        'a threshold not an integer' => [[Post::class => ['views_count' => ['100']]]],
        'not ascending' => [[Post::class => ['views_count' => [1_000, 100]]]],
        'a threshold of zero' => [[Post::class => ['views_count' => [0, 100]]]],
    ]);

    it('rejects a column that is not a counter column', function (): void {
        $config = packageConfig([
            'querying' => ['counters' => [Post::class => ['views_count']]],
            'milestones' => ['thresholds' => [Post::class => ['likes' => [100]]]],
        ]);

        expect(fn (): array => $config->milestones())
            ->toThrow(InvalidConfiguration::class, 'names the `likes` column of `'.Post::class.'`, which is not one of its counter columns');
    });

    it('rejects a counter column over a period', function (): void {
        $config = packageConfig([
            'querying' => ['counters' => [Post::class => ['weekly' => ['period' => '7d']]]],
            'milestones' => ['thresholds' => [Post::class => ['weekly' => [100]]]],
        ]);

        expect(fn (): array => $config->milestones())
            ->toThrow(InvalidConfiguration::class, 'names the `weekly` column of `'.Post::class.'`, which counts a period');
    });
});

describe('spikes', function (): void {
    it('reads the options of each model as given', function (): void {
        $config = packageConfig(['spikes' => ['table' => 'post_spikes', 'types' => [Post::class => ['window' => '1h', 'drops' => true]]]]);

        expect($config->spikes())->toBe([Post::class => ['window' => '1h', 'drops' => true]])
            ->and($config->spikesTable())->toBe('post_spikes')
            ->and(packageConfig()->spikes())->toBeEmpty();
    });

    it('rejects anything but viewable models mapped to known options', function (mixed $value): void {
        expect(fn (): array => packageConfig(['spikes' => ['types' => $value]])->spikes())
            ->toThrow(InvalidConfiguration::class, 'The `eloquent-viewable.spikes.types` config value must map viewable model classes to options of');
    })->with([
        'string' => ['posts'],
        'a class by number' => [[['window' => '1h']]],
        'not a model' => [[Config::class => []]],
        'not a viewable' => [[SoftDeletableView::class => []]],
        'options not a map' => [[Post::class => '1h']],
        'unknown option' => [[Post::class => ['period' => '1h']]],
    ]);
});

describe('trending', function (): void {
    it('reads the trending settings', function (): void {
        $config = packageConfig(['querying' => ['trending' => [
            'curve' => Post::class,
            'half_life' => '6h',
            'step' => '1d',
            'max_steps' => 200,
        ]]]);

        expect($config->trendingCurve())->toBe(Post::class)
            ->and($config->trendingHalfLife()->shorthand())->toBe('6h')
            ->and($config->trendingStep())->toBe(Granularity::Day)
            ->and($config->trendingMaxSteps())->toBe(200);
    });

    it('reads no curve, an hourly step and an automatic step', function (): void {
        expect(packageConfig())->trendingCurve()->toBeNull()->trendingStep()->toBeNull()
            ->and(packageConfig(['querying' => ['trending' => ['step' => 'auto']]]))->trendingStep()->toBeNull()
            ->and(packageConfig(['querying' => ['trending' => ['step' => '1h']]]))->trendingStep()->toBe(Granularity::Hour);
    });

    it('refuses a curve that is not a class', function (mixed $curve): void {
        packageConfig(['querying' => ['trending' => ['curve' => $curve]]])->trendingCurve();
    })->throws(InvalidConfiguration::class, 'querying.trending.curve` config value must be the name of a class')->with([
        'a missing class' => 'App\Curves\Missing',
        'a number' => 7,
    ]);

    it('refuses a half-life that is not a duration', function (mixed $halfLife): void {
        packageConfig(['querying' => ['trending' => ['half_life' => $halfLife]]])->trendingHalfLife();
    })->throws(InvalidConfiguration::class, 'querying.trending.half_life` config value must be a duration such as `30d` or `2y`, ')->with([
        'null' => null,
        'a number' => 24,
        'an unknown unit' => '1 day',
    ]);

    it('refuses a step other than auto, an hour or a day', function (): void {
        packageConfig(['querying' => ['trending' => ['step' => '1w']]])->trendingStep();
    })->throws(InvalidConfiguration::class, 'querying.trending.step` config value must be one of `auto`, `1h`, `1d`, `"1w"` given.');

    it('refuses a maximum number of steps below one', function (): void {
        packageConfig(['querying' => ['trending' => ['max_steps' => 0]]])->trendingMaxSteps();
    })->throws(InvalidConfiguration::class, 'querying.trending.max_steps');
});

it('reads the doctor checks', function (): void {
    expect(packageConfig(['doctor' => ['checks' => [Post::class]]]))->doctorChecks()->toBe([Post::class])
        ->and(packageConfig())->doctorChecks()->toBe([]);
});

it('rejects doctor checks that are not a list of classes', function (mixed $value, string $described): void {
    expect(fn (): array => packageConfig(['doctor' => ['checks' => $value]])->doctorChecks())
        ->toThrow(InvalidConfiguration::class, "The `eloquent-viewable.doctor.checks` config value must be a list of class names, {$described} given.");
})->with([
    'a string' => ['App\\Check', '`"App\\\\Check"`'],
    'a number in the list' => [[1], '`1`'],
    'a class that does not exist' => [['App\\Missing'], '`"App\\\\Missing"`'],
]);

it('reads the sample settings', function (): void {
    expect(packageConfig(['doctor' => ['sample' => ['enabled' => true, 'store' => 'redis', 'key' => 'samples', 'crawler_share' => 0.25]]]))
        ->sampleEnabled()->toBeTrue()
        ->sampleCacheStore()->toBe('redis')
        ->sampleKey()->toBe('samples')
        ->sampleCrawlerShare()->toBe(0.25)
        ->and(packageConfig(['doctor' => ['sample' => ['crawler_share' => 1]]]))
        ->sampleEnabled()->toBeFalse()
        ->sampleCacheStore()->toBeNull()
        ->sampleCrawlerShare()->toBe(1.0);
});

it('rejects a crawler share outside of 0 and 1', function (mixed $value, string $described): void {
    expect(fn (): float => packageConfig(['doctor' => ['sample' => ['crawler_share' => $value]]])->sampleCrawlerShare())
        ->toThrow(InvalidConfiguration::class, "The `eloquent-viewable.doctor.sample.crawler_share` config value must be a number above 0 and at most 1, {$described} given.");
})->with([
    'a string' => ['half', '`"half"`'],
    'zero' => [0, '`0`'],
    'above one' => [1.5, '`1.5`'],
]);

it('reads the presence settings', function (): void {
    $config = packageConfig(['presence' => [
        'enabled' => true,
        'driver' => 'array',
        'window' => 120,
        'precision' => 'approximate',
        'heartbeat' => 30,
        'expose_count' => true,
        'viewers' => true,
        'max_candidates' => 50,
        'redis' => ['connection' => 'live', 'prefix' => 'live'],
    ]]);

    expect($config)
        ->presenceEnabled()->toBeTrue()
        ->presenceDriver()->toBe('array')
        ->presenceWindow()->toBe(120)
        ->presencePrecision()->toBe('approximate')
        ->presenceHeartbeat()->toBe(30)
        ->presenceExposesCount()->toBeTrue()
        ->presenceTracksViewers()->toBeTrue()
        ->presenceMaxCandidates()->toBe(50)
        ->presenceRedisConnection()->toBe('live')
        ->presenceRedisPrefix()->toBe('live')
        ->and(packageConfig())
        ->presenceEnabled()->toBeFalse()
        ->presencePrecision()->toBe('exact')
        ->presenceExposesCount()->toBeFalse()
        ->presenceTracksViewers()->toBeFalse()
        ->presenceRedisConnection()->toBeNull();
});

it('rejects an unknown presence precision', function (): void {
    expect(fn (): string => packageConfig(['presence' => ['precision' => 'roughly']])->presencePrecision())
        ->toThrow(InvalidConfiguration::class, 'The `eloquent-viewable.presence.precision` config value must be one of `exact`, `approximate`, `"roughly"` given.');
});

it('reads the recommendation settings', function (): void {
    $config = packageConfig(['querying' => ['recommendations' => ['max_seeds' => '5', 'max_visitors' => null, 'half_life' => '2d', 'similarity' => 'count']]]);

    expect($config)
        ->recommendationsMaxSeeds()->toBe(5)
        ->recommendationsMaxVisitors()->toBeNull()
        ->recommendationsSimilarity()->toBe('count')
        ->and($config->recommendationsHalfLife()->shorthand())->toBe('2d')
        ->and(packageConfig(['querying' => ['recommendations' => ['max_visitors' => 50]]])->recommendationsMaxVisitors())->toBe(50)
        ->and(packageConfig()->recommendationsSimilarity())->toBe('cosine');
});

it('refuses a recommendation max_visitors that is not a positive integer or null', function (mixed $value): void {
    packageConfig(['querying' => ['recommendations' => ['max_visitors' => $value]]])->recommendationsMaxVisitors();
})->with([0, 'many', 1.5])->throws(InvalidConfiguration::class, 'The `eloquent-viewable.querying.recommendations.max_visitors` config value must be a positive integer or null');

it('refuses a recommendation half_life that is not a duration', function (mixed $value): void {
    packageConfig(['querying' => ['recommendations' => ['half_life' => $value]]])->recommendationsHalfLife();
})->with([null, 'soon', 7])->throws(InvalidConfiguration::class, 'querying.recommendations.half_life');

it('refuses an unknown similarity', function (): void {
    packageConfig(['querying' => ['recommendations' => ['similarity' => 'jaccard']]])->recommendationsSimilarity();
})->throws(InvalidConfiguration::class, 'querying.recommendations.similarity');

it('reads the pairs settings', function (): void {
    $config = packageConfig(['querying' => ['pairs' => ['enabled' => true, 'table' => 'pairs', 'period' => '30d', 'max_pairs' => '25']]]);

    expect($config)
        ->pairsEnabled()->toBeTrue()
        ->pairsTable()->toBe('pairs')
        ->pairsMaxPairs()->toBe(25)
        ->and($config->pairsPeriod()->shorthand())->toBe('30d')
        ->and(packageConfig()->pairsEnabled())->toBeFalse();
});

it('reads the dimensions with their classes and options', function (): void {
    expect(packageConfig(['dimensions' => ['definitions' => [
        'source' => Source::class,
        'country' => [Country::class, 'resolver' => HeaderCountry::class],
    ]]]))->dimensions()->toBe([
        'source' => ['class' => Source::class, 'options' => []],
        'country' => ['class' => Country::class, 'options' => ['resolver' => HeaderCountry::class]],
    ])->and(packageConfig())->dimensions()->toBe([]);
});

it('refuses dimensions it cannot read', function (mixed $value, string $message): void {
    expect(fn (): array => packageConfig(['dimensions' => ['definitions' => $value]])->dimensions())
        ->toThrow(InvalidConfiguration::class, $message);
})->with([
    'not an array' => ['source', 'must map dimension names'],
    'a list' => [[Source::class], 'must map dimension names'],
    'no class' => [['source' => 42], 'must name a class'],
    'an unknown class' => [['source' => 'App\Missing'], 'must name a class'],
    'options without a class' => [['source' => ['personal' => true]], 'must name a class'],
    'an option without a name' => [['source' => [Source::class, true]], 'must give every option after the class a name'],
]);

it('reads the internal hosts', function (): void {
    expect(packageConfig(['dimensions' => ['internal_hosts' => ['example.com', 'shop.example']]]))->internalHosts()->toBe(['example.com', 'shop.example'])
        ->and(packageConfig())->internalHosts()->toBe([]);
});

it('reads the source hosts and aliases, lowercased', function (): void {
    $config = packageConfig(['dimensions' => [
        'sources' => ['News.Example.com' => ['Example News', 'referral']],
        'source_aliases' => ['NL' => 'Newsletter'],
    ]]);

    expect($config->sourceHosts())->toBe(['news.example.com' => ['Example News', 'referral']])
        ->and($config->sourceAliases())->toBe(['nl' => 'Newsletter'])
        ->and(packageConfig())->sourceHosts()->toBe([])
        ->and(packageConfig())->sourceAliases()->toBe([]);
});

it('refuses source hosts it cannot read', function (mixed $value): void {
    expect(fn (): array => packageConfig(['dimensions' => ['sources' => $value]])->sourceHosts())
        ->toThrow(InvalidConfiguration::class, 'must map hosts to a pair of a source name and a medium');
})->with([
    'not an array' => ['example.com'],
    'a list' => [[['Example', 'referral']]],
    'not a pair' => [['example.com' => 'Example']],
    'one of a pair' => [['example.com' => ['Example']]],
    'a keyed pair' => [['example.com' => ['name' => 'Example', 'medium' => 'referral']]],
    'a name that is not a string' => [['example.com' => [1, 'referral']]],
    'a medium that is not a string' => [['example.com' => ['Example', null]]],
]);

it('refuses source aliases it cannot read', function (mixed $value): void {
    expect(fn (): array => packageConfig(['dimensions' => ['source_aliases' => $value]])->sourceAliases())
        ->toThrow(InvalidConfiguration::class, 'must map strings to strings');
})->with([
    'not an array' => ['nl'],
    'a list' => [['Newsletter']],
    'a name that is not a string' => [['nl' => 1]],
]);

it('reads the dimensions folded into rollups', function (): void {
    expect(packageConfig(['retention' => ['rollups' => ['dimensions' => ['source', 'device']]]]))->rollupDimensions()->toBe(['source', 'device'])
        ->and(packageConfig())->rollupDimensions()->toBe([]);
});
