<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Dimensions;

use CyrildeWit\EloquentViewable\Dimensions\Contracts\Dimension;

/**
 * A dimension under the name it is listed by in config.
 */
final readonly class DimensionDefinition
{
    public function __construct(
        public string $name,
        public Dimension $dimension,
    ) {}

    /**
     * The normalised value for this view, or null.
     */
    public function resolve(DimensionInput $input): ?string
    {
        return Normaliser::normalise($this->dimension->resolve($input));
    }

    public function storage(): Storage
    {
        return $this->dimension->storage();
    }

    public function isColumn(): bool
    {
        return $this->storage()->isColumn();
    }

    /**
     * The column, or the JSON path, a query reads the value from.
     */
    public function target(): string
    {
        return $this->storage()->target($this->name);
    }

    public function maxValues(): ?int
    {
        return $this->dimension->maxValues();
    }

    public function personal(): bool
    {
        return $this->dimension->personal();
    }
}
