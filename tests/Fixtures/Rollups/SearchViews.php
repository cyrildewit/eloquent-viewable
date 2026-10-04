<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Tests\Fixtures\Rollups;

use CyrildeWit\EloquentViewable\Querying\Rollups\Rollup;

/**
 * Keeps every view, with the defaults for everything but its tiers.
 */
final class SearchViews extends Rollup
{
    #[\Override]
    public string $name = 'search';

    /** @return array<string, ?string> */
    public function tiers(): array
    {
        return ['month' => null];
    }
}
