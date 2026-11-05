<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Dimensions\Contracts;

use CyrildeWit\EloquentViewable\Dimensions\DimensionInput;

/**
 * Implement this to look up the country of a view another way, such as
 * against a local GeoIP database.
 */
interface CountryResolver
{
    /**
     * The ISO 3166-1 alpha-2 code, in any case, or null when it is unknown.
     */
    public function country(DimensionInput $input): ?string;
}
