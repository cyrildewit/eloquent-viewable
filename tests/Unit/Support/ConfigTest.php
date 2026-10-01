<?php

declare(strict_types=1);

use CyrildeWit\EloquentViewable\Exceptions\InvalidConfiguration;
use CyrildeWit\EloquentViewable\Support\Config;
use Illuminate\Config\Repository;

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
    expect(packageConfig(['cache' => ['key' => 'views', 'store' => 'redis']]))
        ->cacheKey()->toBe('views')
        ->cacheStore()->toBe('redis')
        ->and(packageConfig())->cacheStore()->toBeNull();
});

it('reads the interval cap', function (): void {
    expect(packageConfig(['max_intervals' => '500']))->maxIntervals()->toBe(500);
});

it('reads the queue settings', function (): void {
    expect(packageConfig(['queue' => ['enabled' => true, 'connection' => 'sqs', 'queue' => 'views']]))
        ->queueEnabled()->toBeTrue()
        ->queueConnection()->toBe('sqs')
        ->queueName()->toBe('views')
        ->and(packageConfig())
        ->queueEnabled()->toBeFalse()
        ->queueConnection()->toBeNull()
        ->queueName()->toBeNull();
});

it('reads the recording settings', function (): void {
    expect(packageConfig([
        'cooldown' => ['key' => 'cooldowns'],
        'ignore_bots' => false,
        'honor_dnt' => true,
        'visitor_cookie_key' => 'visitor',
        'ignored_ip_addresses' => ['127.0.0.1', '10.0.0.1'],
    ]))
        ->cooldownKey()->toBe('cooldowns')
        ->ignoreBots()->toBeFalse()
        ->honorDoNotTrack()->toBeTrue()
        ->visitorCookieKey()->toBe('visitor')
        ->ignoredIpAddresses()->toBe(['127.0.0.1', '10.0.0.1']);
});

it('falls back to the defaults of the recording settings', function (): void {
    expect(packageConfig())
        ->ignoreBots()->toBeTrue()
        ->honorDoNotTrack()->toBeFalse()
        ->ignoredIpAddresses()->toBe([]);
});

it('rejects an interval cap that is not a positive integer', function (mixed $value, string $described): void {
    expect(fn (): int => packageConfig(['max_intervals' => $value])->maxIntervals())
        ->toThrow(InvalidConfiguration::class, "The `eloquent-viewable.max_intervals` config value must be a positive integer, {$described} given.");
})->with([
    'zero' => [0, '`0`'],
    'negative' => [-1, '`-1`'],
    'float' => [1.5, '`1.5`'],
    'word' => ['many', '`"many"`'],
    'null' => [null, 'null'],
    'array' => [[], 'array'],
]);

it('rejects an empty key', function (string $method, string $key): void {
    expect(fn (): string => packageConfig()->{$method}())
        ->toThrow(InvalidConfiguration::class, "The `eloquent-viewable.{$key}` config value must be a non-empty string, null given.")
        ->and(fn (): string => packageConfig(['cache' => ['key' => ''], 'cooldown' => ['key' => ''], 'visitor_cookie_key' => ''])->{$method}())
        ->toThrow(InvalidConfiguration::class, "The `eloquent-viewable.{$key}` config value must be a non-empty string, `\"\"` given.");
})->with([
    'cache key' => ['cacheKey', 'cache.key'],
    'cooldown key' => ['cooldownKey', 'cooldown.key'],
    'visitor cookie key' => ['visitorCookieKey', 'visitor_cookie_key'],
]);
