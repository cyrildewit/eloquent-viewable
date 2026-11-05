<?php

declare(strict_types=1);

use CyrildeWit\EloquentViewable\Dimensions\Dimension;
use CyrildeWit\EloquentViewable\Dimensions\DimensionDefinition;
use CyrildeWit\EloquentViewable\Dimensions\DimensionInput;
use CyrildeWit\EloquentViewable\Dimensions\ResolvedDimensions;

function storedAt(?string $json): Dimension
{
    return new class(json: $json) extends Dimension
    {
        public function resolve(DimensionInput $input): ?string
        {
            return null;
        }
    };
}

it('is empty without dimensions', function (): void {
    $resolved = new ResolvedDimensions;

    expect($resolved->all())->toBeEmpty()
        ->and($resolved->columns())->toBeEmpty()
        ->and($resolved->context(['ab' => 'b']))->toBe(['ab' => 'b'])
        ->and($resolved->context(null))->toBeNull();
});

it('splits the values by where they are kept', function (): void {
    $resolved = new ResolvedDimensions([
        'source' => [new DimensionDefinition('source', storedAt(null)), 'Google'],
        'device' => [new DimensionDefinition('device', storedAt(null)), null],
        'plan' => [new DimensionDefinition('plan', storedAt('context->plan')), 'pro'],
        'seat' => [new DimensionDefinition('seat', storedAt('context->billing->seat')), 'team'],
        'tier' => [new DimensionDefinition('tier', storedAt('context->billing->tier')), null],
    ]);

    expect($resolved->all())->toBe(['source' => 'Google', 'device' => null, 'plan' => 'pro', 'seat' => 'team', 'tier' => null])
        ->and($resolved->columns())->toBe(['source' => 'Google', 'device' => null])
        ->and($resolved->context(null))->toBe(['plan' => 'pro', 'billing' => ['seat' => 'team']])
        ->and($resolved->context(['ab' => 'b', 'billing' => 'monthly']))->toBe(['ab' => 'b', 'billing' => ['seat' => 'team'], 'plan' => 'pro']);
});

it('keeps the context of a view without a JSON value', function (): void {
    $resolved = new ResolvedDimensions([
        'plan' => [new DimensionDefinition('plan', storedAt('context->plan')), null],
    ]);

    expect($resolved->context(null))->toBeNull()
        ->and($resolved->context(['ab' => 'b']))->toBe(['ab' => 'b']);
});
