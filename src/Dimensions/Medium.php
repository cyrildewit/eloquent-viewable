<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Dimensions;

use CyrildeWit\EloquentViewable\Dimensions\Sources\SourceList;

/**
 * The kind of source, lowercased: `utm_medium` when the link carries one,
 * then the medium the source list gives the referring host, `referral` for a
 * host it does not know, and `direct` without a referrer. A link tagged with
 * a source but no medium has none, unless the source is a host the list
 * knows.
 */
final class Medium extends Dimension
{
    public const string Direct = 'direct';

    public const string Referral = 'referral';

    public function __construct(
        private readonly SourceList $sources,
        bool $personal = false,
        ?int $maxValues = self::MaxValues,
        ?string $json = null,
    ) {
        parent::__construct($personal, $maxValues, $json);
    }

    public function resolve(DimensionInput $input): ?string
    {
        $medium = $input->landing('utm_medium');

        if ($medium !== null) {
            return mb_strtolower($medium);
        }

        $tagged = $input->landing('utm_source') ?? $input->landing('ref');

        if ($tagged !== null) {
            return $this->sources->find($tagged)[1] ?? null;
        }

        $referrer = $input->externalReferrer();

        if ($referrer === null) {
            return self::Direct;
        }

        return $this->sources->find($referrer)[1] ?? self::Referral;
    }
}
