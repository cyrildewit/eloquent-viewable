<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Dimensions;

use CyrildeWit\EloquentViewable\Dimensions\Contracts\Dimension as DimensionContract;

/**
 * Extend this for a dimension of your own and write `resolve()`. The value is
 * kept in a column named after the dimension, rollups keep its top twenty
 * values per bucket, and anonymising leaves it alone. A config entry changes
 * any of those by name, such as `[PlanDimension::class, 'json' => 'context->plan']`.
 *
 * A subclass with a constructor of its own passes these three on.
 */
abstract class Dimension implements DimensionContract
{
    public const int MaxValues = 20;

    /**
     * @param  ?int  $maxValues  null keeps every value
     * @param  ?string  $json  a path into `context`, such as `context->plan`, instead of a column
     */
    public function __construct(
        protected bool $personal = false,
        protected ?int $maxValues = self::MaxValues,
        protected ?string $json = null,
    ) {}

    public function storage(): Storage
    {
        if ($this->json === null) {
            return Storage::column();
        }

        return Storage::json($this->json);
    }

    public function maxValues(): ?int
    {
        return $this->maxValues;
    }

    public function personal(): bool
    {
        return $this->personal;
    }
}
