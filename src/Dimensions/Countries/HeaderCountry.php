<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Dimensions\Countries;

use CyrildeWit\EloquentViewable\Dimensions\Contracts\CountryResolver;
use CyrildeWit\EloquentViewable\Dimensions\DimensionInput;

/**
 * Reads the country from a header a proxy or CDN in front of the application
 * sets, such as `CloudFront-Viewer-Country`. Only trust a header the proxy
 * overwrites, or a visitor can send any country they like.
 */
final readonly class HeaderCountry implements CountryResolver
{
    public function __construct(
        private string $header = 'CloudFront-Viewer-Country',
    ) {}

    public function country(DimensionInput $input): ?string
    {
        return $input->header($this->header);
    }
}
