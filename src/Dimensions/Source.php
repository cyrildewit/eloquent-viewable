<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Dimensions;

use CyrildeWit\EloquentViewable\Dimensions\Sources\SourceList;

/**
 * Where the visitor came from. A `utm_source` or `ref` value on the link
 * comes first. It is read through the source aliases and then the source
 * list, so `fb` becomes `Facebook`, and is kept as it is when neither knows
 * it. Without one, it is the name the source list gives the referring host,
 * or that host without `www.`. `Direct` when there is neither.
 */
final class Source extends Dimension
{
    public const string Direct = 'Direct';

    public function __construct(
        private readonly SourceList $sources,
        bool $personal = false,
        ?int $maxValues = self::MaxValues,
        ?string $json = null,
    ) {
        parent::__construct($personal, $maxValues, $json);
    }

    public function resolve(DimensionInput $input): string
    {
        $tagged = $input->landing('utm_source') ?? $input->landing('ref');

        if ($tagged !== null) {
            return $this->sources->alias($tagged) ?? $this->sources->find($tagged)[0] ?? $tagged;
        }

        $referrer = $input->externalReferrer();

        if ($referrer === null) {
            return self::Direct;
        }

        return $this->sources->find($referrer)[0] ?? DimensionInput::withoutWww($referrer);
    }
}
