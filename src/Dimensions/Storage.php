<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Dimensions;

/**
 * Where the value of a dimension is kept on a view: a column of its own, named
 * after the dimension, or a path into the JSON `context` column, which needs
 * no migration but cannot use a plain index.
 */
final readonly class Storage
{
    private const string Path = '/^context(->[A-Za-z0-9_]+)+$/';

    private function __construct(
        public ?string $path,
    ) {}

    public static function column(): self
    {
        return new self(null);
    }

    /**
     * A path into the `context` column, such as `context->plan`.
     */
    public static function json(string $path): self
    {
        return new self($path);
    }

    public function isColumn(): bool
    {
        return $this->path === null;
    }

    public function isValid(): bool
    {
        if ($this->path === null) {
            return true;
        }

        return preg_match(self::Path, $this->path) === 1;
    }

    /**
     * The column, or the JSON path, a query reads the value from.
     */
    public function target(string $name): string
    {
        return $this->path ?? $name;
    }

    /**
     * The keys below `context` that hold the value, outermost first.
     *
     * @return list<string>
     */
    public function keys(): array
    {
        if ($this->path === null) {
            return [];
        }

        return array_slice(explode('->', $this->path), 1);
    }
}
