<?php

declare(strict_types=1);

use CyrildeWit\EloquentViewable\Dimensions\DimensionInput;
use CyrildeWit\EloquentViewable\Dimensions\ReferrerHost;

it('keeps the referring host alone', function (?string $referrer, ?string $expected): void {
    expect(new ReferrerHost()->resolve(DimensionInput::fake(referrer: $referrer, appHosts: ['example.com'])))->toBe($expected);
})->with([
    'a host' => ['news.ycombinator.com', 'news.ycombinator.com'],
    'without www' => ['www.google.com', 'google.com'],
    'none' => [null, null],
    'the app itself' => ['www.example.com', null],
]);

it('keeps the defaults of the base dimension', function (): void {
    $dimension = new ReferrerHost;

    expect($dimension->personal())->toBeFalse()
        ->and($dimension->maxValues())->toBe(20)
        ->and($dimension->storage()->isColumn())->toBeTrue();
});
