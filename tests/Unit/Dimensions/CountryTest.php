<?php

declare(strict_types=1);

use CyrildeWit\EloquentViewable\Dimensions\Contracts\CountryResolver;
use CyrildeWit\EloquentViewable\Dimensions\Countries\CloudflareCountry;
use CyrildeWit\EloquentViewable\Dimensions\Countries\HeaderCountry;
use CyrildeWit\EloquentViewable\Dimensions\Country;
use CyrildeWit\EloquentViewable\Dimensions\DimensionInput;

it('reads the country Cloudflare sends by default', function (array $headers, ?string $expected): void {
    expect(new Country()->resolve(DimensionInput::fake(headers: $headers)))->toBe($expected);
})->with([
    'a country' => [['CF-IPCountry' => 'NL'], 'NL'],
    'lowercased' => [['CF-IPCountry' => 'de'], 'DE'],
    'unknown' => [['CF-IPCountry' => 'XX'], null],
    'Tor' => [['CF-IPCountry' => 'T1'], null],
    'not a code' => [['CF-IPCountry' => 'Netherlands'], null],
    'missing' => [[], null],
]);

it('reads the country from a header of your choice', function (): void {
    $input = DimensionInput::fake(headers: ['CloudFront-Viewer-Country' => 'BE', 'X-Country' => 'fr']);

    expect(new Country(HeaderCountry::class)->resolve($input))->toBe('BE')
        ->and(new Country(HeaderCountry::class, header: 'X-Country')->resolve($input))->toBe('FR');
});

it('reads the country from a resolver of your own', function (): void {
    $resolver = new class implements CountryResolver
    {
        public function country(DimensionInput $input): string
        {
            return 'jp';
        }
    };

    expect(new Country($resolver::class)->resolve(DimensionInput::fake()))->toBe('JP');
});

it('refuses a resolver that is not one', function (): void {
    new Country(stdClass::class);
})->throws(InvalidArgumentException::class, 'must implement');

it('refuses a header for a resolver that reads none', function (): void {
    new Country(CloudflareCountry::class, header: 'X-Country');
})->throws(InvalidArgumentException::class, 'only applies to `HeaderCountry`');
