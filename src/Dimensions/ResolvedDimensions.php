<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Dimensions;

use Illuminate\Support\Arr;

/**
 * The value of every dimension for one view, split by where each is kept: the
 * column dimensions as columns of the row, the JSON ones merged into its
 * context.
 */
final readonly class ResolvedDimensions
{
    /** @param  array<string, array{DimensionDefinition, ?string}>  $values  a definition and its value by name */
    public function __construct(
        private array $values = [],
    ) {}

    /**
     * Every value by name, wherever it is kept.
     *
     * @return array<string, ?string>
     */
    public function all(): array
    {
        return array_map(static fn (array $value): ?string => $value[1], $this->values);
    }

    /**
     * The values kept in a column, by column.
     *
     * @return array<string, ?string>
     */
    public function columns(): array
    {
        $columns = [];

        foreach ($this->values as $name => [$definition, $value]) {
            if ($definition->isColumn()) {
                $columns[$name] = $value;
            }
        }

        return $columns;
    }

    /**
     * The context with the values kept in it set at their paths. A dimension
     * without a value leaves its path out, so a view without any keeps the
     * context it had.
     *
     * @param  ?array<string, mixed>  $context
     * @return ?array<string, mixed>
     */
    public function context(?array $context): ?array
    {
        foreach ($this->values as [$definition, $value]) {
            if ($definition->isColumn()) {
                continue;
            }

            if ($value === null) {
                continue;
            }

            $context ??= [];

            // The keys of a path are letters, digits and underscores, so they
            // join with dots safely.
            Arr::set($context, implode('.', $definition->storage()->keys()), $value);
        }

        /** @var ?array<string, mixed> $context */
        return $context;
    }
}
