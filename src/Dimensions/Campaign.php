<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Dimensions;

/**
 * The lowercased `utm_campaign` of the link the visitor followed. Personal by
 * default, because a campaign name can carry an id per recipient; pass
 * `'personal' => false` when yours never do.
 */
final class Campaign extends Dimension
{
    public function __construct(
        bool $personal = true,
        ?int $maxValues = self::MaxValues,
        ?string $json = null,
    ) {
        parent::__construct($personal, $maxValues, $json);
    }

    public function resolve(DimensionInput $input): ?string
    {
        $campaign = $input->landing('utm_campaign');

        if ($campaign === null) {
            return null;
        }

        return mb_strtolower($campaign);
    }
}
