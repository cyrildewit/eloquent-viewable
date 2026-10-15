<?php

declare(strict_types=1);

use CyrildeWit\EloquentViewable\Dimensions\Campaign;
use CyrildeWit\EloquentViewable\Dimensions\DimensionInput;

it('reads the lowercased utm_campaign', function (array $landing, ?string $expected): void {
    expect(new Campaign()->resolve(DimensionInput::fake(landing: $landing)))->toBe($expected);
})->with([
    'a campaign' => [['utm_campaign' => 'Spring-Sale'], 'spring-sale'],
    'unicode' => [['utm_campaign' => 'ÉTÉ'], 'été'],
    'none' => [[], null],
    'blank' => [['utm_campaign' => ' '], null],
]);

it('is personal unless told otherwise', function (): void {
    expect(new Campaign()->personal())->toBeTrue()
        ->and(new Campaign(personal: false)->personal())->toBeFalse();
});
