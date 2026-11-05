<?php

declare(strict_types=1);

use CyrildeWit\EloquentViewable\Exceptions\InvalidConfiguration;
use CyrildeWit\EloquentViewable\Presence\PresenceConfig;
use Illuminate\Config\Repository;

/** @param  array<string, mixed>  $values */
function presenceConfig(array $values = []): PresenceConfig
{
    return new PresenceConfig(new Repository(['eloquent-viewable' => ['presence' => $values]]));
}

it('reads the presence settings', function (): void {
    $config = presenceConfig([
        'enabled' => true,
        'driver' => 'array',
        'window' => 120,
        'precision' => 'approximate',
        'heartbeat' => 30,
        'expose_count' => true,
        'viewers' => true,
        'max_candidates' => 50,
        'redis' => ['connection' => 'live', 'prefix' => 'live'],
    ]);

    expect($config)
        ->enabled()->toBeTrue()
        ->driver()->toBe('array')
        ->window()->toBe(120)
        ->precision()->toBe('approximate')
        ->heartbeat()->toBe(30)
        ->exposesCount()->toBeTrue()
        ->tracksViewers()->toBeTrue()
        ->maxCandidates()->toBe(50)
        ->redisConnection()->toBe('live')
        ->redisPrefix()->toBe('live')
        ->and(presenceConfig())
        ->enabled()->toBeFalse()
        ->precision()->toBe('exact')
        ->exposesCount()->toBeFalse()
        ->tracksViewers()->toBeFalse()
        ->redisConnection()->toBeNull();
});

it('rejects an unknown precision', function (): void {
    expect(fn (): string => presenceConfig(['precision' => 'roughly'])->precision())
        ->toThrow(InvalidConfiguration::class, 'The `eloquent-viewable.presence.precision` config value must be one of `exact`, `approximate`, `"roughly"` given.');
});

it('rejects a positive integer key that is not one', function (string $method, string $key): void {
    expect(fn (): int => presenceConfig([$key => 0])->{$method}())
        ->toThrow(InvalidConfiguration::class, "The `eloquent-viewable.presence.{$key}` config value must be a positive integer, `0` given.");
})->with([
    'window' => ['window', 'window'],
    'heartbeat' => ['heartbeat', 'heartbeat'],
    'candidates' => ['maxCandidates', 'max_candidates'],
]);

it('rejects a string key that is not one', function (string $method, string $key, mixed $value, string $message): void {
    $values = [];
    data_set($values, $key, $value);

    expect(fn (): ?string => presenceConfig($values)->{$method}())
        ->toThrow(InvalidConfiguration::class, "The `eloquent-viewable.presence.{$key}` config value must be {$message}.");
})->with([
    'driver' => ['driver', 'driver', '', 'a non-empty string, `""` given'],
    'redis prefix' => ['redisPrefix', 'redis.prefix', '', 'a non-empty string, `""` given'],
    'redis connection' => ['redisConnection', 'redis.connection', 1, 'a string or null, `1` given'],
]);
