<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Dimensions\Countries;

use CyrildeWit\EloquentViewable\Dimensions\Contracts\CountryResolver;
use CyrildeWit\EloquentViewable\Dimensions\DimensionInput;

/**
 * Reads the `CF-IPCountry` header Cloudflare adds when IP geolocation is on.
 * Cloudflare sends `XX` for an unknown country, which this reads as no country.
 * It sends `T1` for Tor, which `Country` drops because it is not a two-letter
 * code.
 */
final readonly class CloudflareCountry implements CountryResolver
{
    public const string Header = 'CF-IPCountry';

    public function country(DimensionInput $input): ?string
    {
        $country = $input->header(self::Header);

        if ($country === null) {
            return null;
        }

        if (strtoupper($country) === 'XX') {
            return null;
        }

        return $country;
    }
}
