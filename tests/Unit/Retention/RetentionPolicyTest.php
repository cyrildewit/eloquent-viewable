<?php

declare(strict_types=1);

use CyrildeWit\EloquentViewable\Exceptions\InvalidConfiguration;
use CyrildeWit\EloquentViewable\Retention\RetentionPolicy;
use CyrildeWit\EloquentViewable\Support\Config;
use CyrildeWit\EloquentViewable\Support\Duration;
use Illuminate\Config\Repository;

/** @param  array<string, mixed>  $retention */
function retentionPolicy(array $retention): RetentionPolicy
{
    return RetentionPolicy::fromConfig(new Config(new Repository(['eloquent-viewable' => ['retention' => $retention]])));
}

it('reads the retention config', function (): void {
    $policy = retentionPolicy([
        'anonymise' => ['after' => '30d', 'columns' => ['context', 'visitor']],
        'prune' => ['after' => '90d'],
        'chunk' => 100,
    ]);

    expect($policy->anonymiseAfter?->shorthand())->toBe('30d')
        ->and($policy->anonymiseColumns)->toBe(['visitor', 'context'])
        ->and($policy->pruneAfter?->shorthand())->toBe('90d')
        ->and($policy->chunk)->toBe(100);
});

it('keeps nothing from happening when nothing is set', function (): void {
    expect(retentionPolicy(['chunk' => 5_000]))
        ->anonymiseAfter->toBeNull()
        ->pruneAfter->toBeNull()
        ->anonymiseColumns->toBe(['visitor', 'viewer', 'context']);
});

it('accepts anonymising and pruning after the same duration', function (): void {
    expect(retentionPolicy(['anonymise' => ['after' => '4w'], 'prune' => ['after' => '28d'], 'chunk' => 1]))
        ->anonymiseAfter->toBeInstanceOf(Duration::class);
});

it('refuses to anonymise views after they are pruned', function (): void {
    expect(fn (): RetentionPolicy => retentionPolicy(['anonymise' => ['after' => '1y'], 'prune' => ['after' => '90d'], 'chunk' => 1]))
        ->toThrow(InvalidConfiguration::class, 'The `eloquent-viewable.retention.anonymise.after` config value must not be longer than `retention.prune.after`, `1y` and `90d` given.');
});
