<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use Carbon\CarbonInterval;
use CyrildeWit\EloquentViewable\Exceptions\InvalidConfiguration;
use CyrildeWit\EloquentViewable\Querying\Exceptions\InvalidDecay;
use CyrildeWit\EloquentViewable\Querying\Ranking\Curves\ExponentialDecay;
use CyrildeWit\EloquentViewable\Querying\Ranking\Curves\LinearDecay;
use CyrildeWit\EloquentViewable\Querying\Ranking\Curves\Window;
use CyrildeWit\EloquentViewable\Querying\Ranking\DecayFactory;
use CyrildeWit\EloquentViewable\Support\Config;
use CyrildeWit\EloquentViewable\Support\Granularity;
use CyrildeWit\EloquentViewable\Support\ViewsQuery;
use Illuminate\Config\Repository;
use Illuminate\Container\Container;

beforeEach(function (): void {
    CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-10-04 13:20:00', 'UTC'));
});

/** @param  array<string, mixed>  $trending */
function decays(array $trending = [], ?string $rollupTimezone = null, ?Container $container = null): DecayFactory
{
    $config = new Config(new Repository(['eloquent-viewable' => [
        'querying' => ['trending' => [...['curve' => null, 'half_life' => '1d', 'step' => 'auto', 'max_steps' => 500], ...$trending]],
        'retention' => ['rollups' => ['timezone' => $rollupTimezone]],
    ]]));

    return new DecayFactory($config, $container ?? new Container);
}

it('halves the weight every configured half-life by default', function (): void {
    expect(decays()->make(new ViewsQuery)->identity())->toBe(new ExponentialDecay(CarbonInterval::day())->identity().':hour')
        ->and(decays(['half_life' => '6h'])->make(new ViewsQuery)->steps()[6]->weight)->toBe(500_000);
});

it('takes a half-life for one call', function (): void {
    expect(decays()->make(new ViewsQuery, halfLife: CarbonInterval::hours(2))->steps()[2]->weight)->toBe(500_000);
});

it('takes a curve for one call', function (): void {
    expect(decays()->make(new ViewsQuery, curve: new Window(CarbonInterval::hours(3)))->steps())->toHaveCount(3);
});

it('refuses a half-life and a curve together', function (): void {
    decays()->make(new ViewsQuery, CarbonInterval::day(), new Window(CarbonInterval::day()));
})->throws(InvalidDecay::class, 'Pass either a half-life or a curve, not both.');

it('resolves the configured curve from the container', function (): void {
    $container = new Container;
    $container->bind(LinearDecay::class, fn (): LinearDecay => new LinearDecay(CarbonInterval::hours(4)));

    expect(decays(['curve' => LinearDecay::class], container: $container)->make(new ViewsQuery)->steps())->toHaveCount(4);
});

it('refuses a configured class that is not a curve', function (): void {
    decays(['curve' => stdClass::class])->make(new ViewsQuery);
})->throws(InvalidConfiguration::class, 'The `eloquent-viewable.querying.trending.curve` config value must name a class implementing `CyrildeWit\EloquentViewable\Querying\Ranking\DecayCurve`, `stdClass` does not.');

it('reads the configured step and cap', function (): void {
    expect(decays(['step' => '1d'])->make(new ViewsQuery)->step())->toBe(Granularity::Day);

    decays(['max_steps' => 5])->make(new ViewsQuery);
})->throws(InvalidDecay::class, 'more than the maximum of 5');

it('floors days in the rollup timezone', function (): void {
    expect(decays(['step' => '1d'], 'Europe/Amsterdam')->make(new ViewsQuery)->steps()[0]->start->format('Y-m-d H:i:s'))->toBe('2026-10-03 22:00:00')
        ->and(decays(['step' => '1d'])->make(new ViewsQuery)->steps()[0]->start->format('Y-m-d H:i:s'))->toBe('2026-10-04 00:00:00');
});
