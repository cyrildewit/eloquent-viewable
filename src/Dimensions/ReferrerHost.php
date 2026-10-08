<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Dimensions;

/**
 * The host of the referring page, without `www.`, such as
 * `news.ycombinator.com`. Never its path or query string.
 */
final class ReferrerHost extends Dimension
{
    public function resolve(DimensionInput $input): ?string
    {
        $referrer = $input->externalReferrer();

        if ($referrer === null) {
            return null;
        }

        return DimensionInput::withoutWww($referrer);
    }
}
