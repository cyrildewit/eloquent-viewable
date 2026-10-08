<?php

declare(strict_types=1);

use CyrildeWit\EloquentViewable\Crawlers\Contracts\CrawlerDetector;
use CyrildeWit\EloquentViewable\Crawlers\Detectors\CrawlerDetectAdapter;
use CyrildeWit\EloquentViewable\Dimensions\Campaign;
use CyrildeWit\EloquentViewable\Dimensions\Countries\HeaderCountry;
use CyrildeWit\EloquentViewable\Dimensions\Country;
use CyrildeWit\EloquentViewable\Dimensions\Device;
use CyrildeWit\EloquentViewable\Dimensions\Dimension;
use CyrildeWit\EloquentViewable\Dimensions\DimensionDefinition;
use CyrildeWit\EloquentViewable\Dimensions\DimensionInput;
use CyrildeWit\EloquentViewable\Dimensions\DimensionRegistry;
use CyrildeWit\EloquentViewable\Dimensions\ReferrerHost;
use CyrildeWit\EloquentViewable\Dimensions\Source;
use CyrildeWit\EloquentViewable\Dimensions\Sources\SourceList;
use CyrildeWit\EloquentViewable\Exceptions\InvalidConfiguration;
use CyrildeWit\EloquentViewable\Support\Config;
use Illuminate\Config\Repository;
use Illuminate\Container\Container;

/** @param  array<string, mixed>  $definitions */
function registryOf(array $definitions): DimensionRegistry
{
    $container = new Container;
    $container->instance(SourceList::class, SourceList::shipped());
    $container->bind(CrawlerDetector::class, CrawlerDetectAdapter::class);

    return DimensionRegistry::fromConfig(
        new Config(new Repository(['eloquent-viewable' => ['dimensions' => ['definitions' => $definitions]]])),
        $container,
    );
}

final class PlanDimension extends Dimension
{
    public function resolve(DimensionInput $input): ?string
    {
        $plan = $input->context['plan'] ?? null;

        return is_string($plan) ? "  {$plan}\x07 " : null;
    }
}

it('is empty without dimensions', function (): void {
    $registry = registryOf([]);

    expect($registry->isEmpty())->toBeTrue()
        ->and($registry->all())->toBeEmpty()
        ->and($registry->columns())->toBeEmpty()
        ->and($registry->personal())->toBeEmpty();
});

it('builds every dimension by name', function (): void {
    $registry = registryOf([
        'source' => Source::class,
        'campaign' => Campaign::class,
        'device' => Device::class,
        'country' => [Country::class, 'resolver' => HeaderCountry::class, 'header' => 'X-Country'],
        'plan' => [PlanDimension::class, 'json' => 'context->plan'],
    ]);

    expect($registry->isEmpty())->toBeFalse()
        ->and(array_keys($registry->all()))->toBe(['source', 'campaign', 'device', 'country', 'plan'])
        ->and($registry->find('source'))->toBeInstanceOf(DimensionDefinition::class)
        ->and($registry->find('source')?->dimension)->toBeInstanceOf(Source::class)
        ->and($registry->find('missing'))->toBeNull()
        ->and($registry->columns())->toBe(['source', 'campaign', 'device', 'country'])
        ->and(array_map(static fn (DimensionDefinition $definition): string => $definition->name, $registry->personal()))->toBe(['campaign'])
        ->and($registry->find('country')?->resolve(DimensionInput::fake(headers: ['X-Country' => 'nl'])))->toBe('NL');
});

it('passes options to the constructor by name', function (): void {
    $definition = registryOf(['campaign' => [Campaign::class, 'personal' => false, 'maxValues' => null]])->find('campaign');

    expect($definition?->personal())->toBeFalse()
        ->and($definition?->maxValues())->toBeNull();
});

it('normalises what a dimension resolves', function (): void {
    $definition = registryOf(['plan' => [PlanDimension::class, 'json' => 'context->plan']])->find('plan');

    expect($definition?->resolve(DimensionInput::fake(context: ['plan' => 'pro'])))->toBe('pro')
        ->and($definition?->resolve(DimensionInput::fake()))->toBeNull()
        ->and($definition?->isColumn())->toBeFalse()
        ->and($definition?->target())->toBe('context->plan')
        ->and($definition?->storage()->keys())->toBe(['plan']);
});

it('reads the target of a column dimension from its name', function (): void {
    $definition = registryOf(['referrer_host' => ReferrerHost::class])->find('referrer_host');

    expect($definition?->isColumn())->toBeTrue()
        ->and($definition?->target())->toBe('referrer_host');
});

it('refuses a name that cannot be a column', function (string $name, string $problem): void {
    expect(fn (): DimensionRegistry => registryOf([$name => ReferrerHost::class]))
        ->toThrow(InvalidConfiguration::class, $problem);
})->with([
    'a dash' => ['referrer-host', 'letters, digits and underscores'],
    'a space' => ['referrer host', 'letters, digits and underscores'],
    'too long' => [str_repeat('a', 33), 'at most 32 characters'],
    'an existing column' => ['visitor', 'a column the views table already has'],
    'an existing column in capitals' => ['Viewed_At', 'a column the views table already has'],
]);

it('refuses an entry it cannot build', function (mixed $entry, string $problem): void {
    expect(fn (): DimensionRegistry => registryOf(['plan' => $entry]))
        ->toThrow(InvalidConfiguration::class, $problem);
})->with([
    'not a dimension' => [stdClass::class, 'must name a class implementing'],
    'a misspelled option' => [[Campaign::class, 'personnal' => false], 'has an option `personnal`'],
    'an option of the wrong type' => [[Campaign::class, 'maxValues' => 'many'], 'cannot be built'],
    'a resolver that is not one' => [[Country::class, 'resolver' => stdClass::class], 'cannot be built'],
    'a path outside context' => [[PlanDimension::class, 'json' => 'visitor->plan'], 'at a JSON path such as `context->plan`'],
    'no values per bucket' => [[PlanDimension::class, 'maxValues' => 0], 'at least one value per bucket'],
]);

it('names the entry in the error', function (): void {
    registryOf(['plan' => stdClass::class]);
})->throws(InvalidConfiguration::class, 'The `plan` entry in `eloquent-viewable.dimensions.definitions`');
