<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Dimensions;

use CyrildeWit\EloquentViewable\Dimensions\Contracts\Dimension;
use CyrildeWit\EloquentViewable\Exceptions\InvalidConfiguration;
use CyrildeWit\EloquentViewable\Support\Config;
use Illuminate\Contracts\Container\Container;
use ReflectionClass;
use ReflectionParameter;
use Throwable;

/**
 * The dimensions listed under `dimensions.definitions`, built once from
 * config. A name names the column too, so it is kept to letters, digits and
 * underscores, and may not be a column the views table already has.
 */
final readonly class DimensionRegistry
{
    private const int MaxNameLength = 32;

    private const array ReservedNames = [
        'id',
        'viewable_type',
        'viewable_id',
        'viewer_type',
        'viewer_id',
        'visitor',
        'collection',
        'context',
        'viewed_at',
    ];

    /** @param  array<string, DimensionDefinition>  $definitions */
    public function __construct(
        private array $definitions = [],
    ) {}

    /** @throws InvalidConfiguration */
    public static function fromConfig(Config $config, Container $container): self
    {
        $definitions = [];

        foreach ($config->dimensions() as $name => $entry) {
            self::guardName($name);

            $definitions[$name] = new DimensionDefinition($name, self::build($container, $name, $entry['class'], $entry['options']));
        }

        return new self($definitions);
    }

    /** @return array<string, DimensionDefinition> */
    public function all(): array
    {
        return $this->definitions;
    }

    public function isEmpty(): bool
    {
        return $this->definitions === [];
    }

    public function find(string $name): ?DimensionDefinition
    {
        return $this->definitions[$name] ?? null;
    }

    /**
     * The names of the dimensions kept in a column of their own.
     *
     * @return list<string>
     */
    public function columns(): array
    {
        $columns = [];

        foreach ($this->definitions as $name => $definition) {
            if ($definition->isColumn()) {
                $columns[] = $name;
            }
        }

        return $columns;
    }

    /** @return list<DimensionDefinition> */
    public function personal(): array
    {
        return array_values(array_filter($this->definitions, static fn (DimensionDefinition $definition): bool => $definition->personal()));
    }

    /**
     * The columns anonymising clears. A personal dimension kept in `context`
     * is cleared with it.
     *
     * @return list<string>
     */
    public function personalColumns(): array
    {
        $columns = [];

        foreach ($this->personal() as $definition) {
            if ($definition->isColumn()) {
                $columns[] = $definition->name;
            }
        }

        return $columns;
    }

    /** @throws InvalidConfiguration */
    private static function guardName(string $name): void
    {
        if (preg_match('/^[A-Za-z0-9_]+$/', $name) !== 1) {
            throw InvalidConfiguration::invalidDimension($name, 'must be named with letters, digits and underscores');
        }

        if (strlen($name) > self::MaxNameLength) {
            throw InvalidConfiguration::invalidDimension($name, 'must be named in at most 32 characters');
        }

        if (in_array(strtolower($name), self::ReservedNames, true)) {
            throw InvalidConfiguration::invalidDimension($name, 'is named after a column the views table already has');
        }
    }

    /**
     * The container ignores an argument the constructor does not take, so an
     * option is checked against the parameters first, and a misspelled one
     * fails here rather than going unnoticed. Whatever the constructor throws,
     * such as a `TypeError` for an option of the wrong type, is reported
     * against the entry.
     *
     * @param  class-string  $class
     * @param  array<string, mixed>  $options
     *
     * @throws InvalidConfiguration
     */
    private static function build(Container $container, string $name, string $class, array $options): Dimension
    {
        $parameters = array_map(
            static fn (ReflectionParameter $parameter): string => $parameter->getName(),
            new ReflectionClass($class)->getConstructor()?->getParameters() ?? [],
        );

        foreach (array_keys($options) as $option) {
            if (! in_array($option, $parameters, true)) {
                throw InvalidConfiguration::invalidDimension($name, "has an option `{$option}` that `{$class}` does not take");
            }
        }

        try {
            $dimension = $container->make($class, $options);
        } catch (Throwable $exception) {
            throw InvalidConfiguration::invalidDimension($name, "cannot be built: {$exception->getMessage()}", $exception);
        }

        if (! $dimension instanceof Dimension) {
            $contract = Dimension::class;

            throw InvalidConfiguration::invalidDimension($name, "must name a class implementing `{$contract}`");
        }

        self::guardDimension($name, $dimension);

        return $dimension;
    }

    /** @throws InvalidConfiguration */
    private static function guardDimension(string $name, Dimension $dimension): void
    {
        if (! $dimension->storage()->isValid()) {
            throw InvalidConfiguration::invalidDimension($name, 'must keep its value in a column, or at a JSON path such as `context->plan`');
        }

        $maxValues = $dimension->maxValues();

        if ($maxValues === null) {
            return;
        }

        if ($maxValues < 1) {
            throw InvalidConfiguration::invalidDimension($name, 'must keep at least one value per bucket, or null for every value');
        }
    }
}
