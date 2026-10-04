<?php

declare(strict_types=1);

use CyrildeWit\EloquentViewable\Exceptions\InvalidConfiguration;
use CyrildeWit\EloquentViewable\Models\View;
use CyrildeWit\EloquentViewable\Recording\Guards\EnforceCooldown;
use CyrildeWit\EloquentViewable\Recording\Guards\IgnoreCrawlers;
use CyrildeWit\EloquentViewable\Support\Config;
use CyrildeWit\EloquentViewable\Support\Duration;
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
    'visitor cookie name' => ['visitorCookieName', 'visitor.cookie.name'],
    'fingerprint key' => ['fingerprintKey', 'visitor.fingerprint.key'],
    'store driver' => ['storeDriver', 'recording.store.driver'],
    'source driver' => ['sourceDriver', 'querying.source.driver'],
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
        ->anonymiseColumns()->toBe(['visitor', 'viewer', 'context']);
});

it('rejects a retention duration that is not a shorthand', function (mixed $value, string $described): void {
    expect(fn (): mixed => packageConfig(['retention' => ['prune' => ['after' => $value]]])->pruneAfter())
        ->toThrow(InvalidConfiguration::class, "The `eloquent-viewable.retention.prune.after` config value must be a duration such as `30d` or `2y`, or null, {$described} given.");
})->with([
    'unknown unit' => ['90x', '`"90x"`'],
    'zero' => ['0d', '`"0d"`'],
    'integer' => [90, '`90`'],
]);

it('rejects anonymised columns that are not visitor, viewer or context', function (mixed $value, string $described): void {
    expect(fn (): array => packageConfig(['retention' => ['anonymise' => ['columns' => $value]]])->anonymiseColumns())
        ->toThrow(InvalidConfiguration::class, "The `eloquent-viewable.retention.anonymise.columns` config value must be a list of `visitor`, `viewer`, `context`, {$described} given.");
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

it('rejects counters that are not viewable models mapped to columns', function (mixed $value): void {
    expect(fn (): array => packageConfig(['querying' => ['counters' => $value]])->counters())
        ->toThrow(InvalidConfiguration::class, 'The `eloquent-viewable.querying.counters` config value must map viewable model classes to their counter columns');
})->with([
    'string' => ['views_count'],
    'list' => [[['views_count']]],
    'not a viewable' => [[SoftDeletableView::class => ['views_count']]],
    'no columns' => [[Post::class => []]],
    'a column by number' => [[Post::class => [1]]],
    'unknown option' => [[Post::class => ['views_count' => ['viewer' => 1]]]],
    'period not a string' => [[Post::class => ['views_count' => ['period' => 7]]]],
    'collection not a string' => [[Post::class => ['views_count' => ['collection' => 1]]]],
]);
