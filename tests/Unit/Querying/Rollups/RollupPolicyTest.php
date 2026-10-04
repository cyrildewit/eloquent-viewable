<?php

declare(strict_types=1);

use CyrildeWit\EloquentViewable\Exceptions\InvalidConfiguration;
use CyrildeWit\EloquentViewable\Querying\Rollups\Grouping;
use CyrildeWit\EloquentViewable\Querying\Rollups\RollupPolicy;
use CyrildeWit\EloquentViewable\Querying\Rollups\Tier;
use CyrildeWit\EloquentViewable\Support\Config;
use Illuminate\Config\Repository;

/** @param  array<string, mixed>  $retention */
function rollupPolicy(array $rollups = [], array $retention = []): RollupPolicy
{
    return RollupPolicy::fromConfig(new Config(new Repository(['eloquent-viewable' => ['retention' => [
        ...$retention,
        'rollups' => ['table' => 'view_rollups', 'settle' => '1h', ...$rollups],
    ]]])));
}

it('is off without tiers', function (): void {
    $policy = rollupPolicy();

    expect($policy->isEnabled())->toBeFalse()
        ->and($policy->tiers())->toBeEmpty()
        ->and($policy->table)->toBe('view_rollups')
        ->and($policy->strict)->toBeFalse()
        ->and($policy->timezone->getName())->toBe(date_default_timezone_get())
        ->and($policy->groupings)->toBe([Grouping::Viewable, Grouping::ViewableCollection, Grouping::Type]);
});

it('orders the tiers coarse to fine whatever order they are listed in', function (): void {
    $policy = rollupPolicy(['tiers' => ['day' => '2y', 'year' => null, 'month' => null]]);

    expect($policy->isEnabled())->toBeTrue()
        ->and($policy->tiers())->toBe([Tier::Year, Tier::Month, Tier::Day])
        ->and($policy->keep(Tier::Day)?->shorthand())->toBe('2y')
        ->and($policy->keep(Tier::Month))->toBeNull()
        ->and($policy->coarserThan(Tier::Day))->toBe(Tier::Month)
        ->and($policy->coarserThan(Tier::Year))->toBeNull();
});

it('reads the timezone, groupings and strictness', function (): void {
    $policy = rollupPolicy(['tiers' => ['day' => null], 'timezone' => 'Europe/Amsterdam', 'groupings' => ['type', 'viewable'], 'strict' => true]);

    expect($policy->timezone->getName())->toBe('Europe/Amsterdam')
        ->and($policy->groupings)->toBe([Grouping::Viewable, Grouping::Type])
        ->and($policy->keeps(Grouping::Type))->toBeTrue()
        ->and($policy->keeps(Grouping::ViewableCollection))->toBeFalse()
        ->and($policy->strict)->toBeTrue();
});

it('refuses a coarser tier kept shorter than a finer one', function (array $tiers, string $coarser, string $finer): void {
    expect(fn (): RollupPolicy => rollupPolicy(['tiers' => $tiers]))
        ->toThrow(InvalidConfiguration::class, "The `{$coarser}` rollup tier in `eloquent-viewable.retention.rollups.tiers` must be kept at least as long as the finer `{$finer}` tier");
})->with([
    'shorter' => [['day' => '2y', 'month' => '1y'], 'month', 'day'],
    'finer forever' => [['day' => null, 'month' => '5y'], 'month', 'day'],
]);

it('accepts a coarser tier kept as long as a finer one', function (): void {
    expect(rollupPolicy(['tiers' => ['day' => '1y', 'month' => '12m']])->tiers())->toBe([Tier::Month, Tier::Day]);
});

it('refuses to prune views before the coarsest tier can fold them', function (): void {
    expect(fn (): RollupPolicy => rollupPolicy(['tiers' => ['day' => '1y', 'month' => null]], ['prune' => ['after' => '30d']]))
        ->toThrow(InvalidConfiguration::class, 'The `eloquent-viewable.retention.prune.after` config value must be longer than one `month` plus `retention.rollups.settle`, `30d` and `1h` given.');
});

it('accepts pruning once the coarsest tier has had time to fold', function (): void {
    expect(rollupPolicy(['tiers' => ['day' => null], 'settle' => null], ['prune' => ['after' => '1d']])->settle)->toBeNull()
        ->and(rollupPolicy(['tiers' => ['day' => '1y', 'month' => null]], ['prune' => ['after' => '90d']])->isEnabled())->toBeTrue();
});

it('names a missing settle in the message', function (): void {
    expect(fn (): RollupPolicy => rollupPolicy(['tiers' => ['month' => null], 'settle' => null], ['prune' => ['after' => '7d']]))
        ->toThrow(InvalidConfiguration::class, '`7d` and `null` given');
});
