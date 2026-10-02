<?php

declare(strict_types=1);

use CyrildeWit\EloquentViewable\Exceptions\InvalidConfiguration;
use CyrildeWit\EloquentViewable\Models\View;
use CyrildeWit\EloquentViewable\Recording\Guards\EnforceCooldown;
use CyrildeWit\EloquentViewable\Recording\Guards\IgnoreCrawlers;
use CyrildeWit\EloquentViewable\Support\Config;
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
        ->and(packageConfig())->visitorIdentity()->toBe('cookie');
});

it('rejects an unknown visitor identity', function (mixed $value, string $described): void {
    expect(fn (): string => packageConfig(['visitor' => ['identity' => $value]])->visitorIdentity())
        ->toThrow(InvalidConfiguration::class, "The `eloquent-viewable.visitor.identity` config value must be one of `cookie`, `viewer`, {$described} given.");
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
