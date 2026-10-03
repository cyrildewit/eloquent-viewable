<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Support;

use CyrildeWit\EloquentViewable\Contracts\Viewable;
use CyrildeWit\EloquentViewable\Exceptions\InvalidViewable;

/**
 * Saved viewables of one type, counted together, keyed by their key. PHP
 * turns a numeric string key into an integer, so `5` and `'5'` are one
 * viewable, as they are to the database. A viewable without a key stands for
 * its whole type elsewhere, which means nothing inside a set, and two types
 * would share keys, so both are refused.
 */
final readonly class ViewableSet
{
    /** @param  array<int|string, Viewable>  $viewables */
    private function __construct(private array $viewables) {}

    /**
     * @param  iterable<mixed>  $viewables
     *
     * @throws InvalidViewable
     */
    public static function of(iterable $viewables): self
    {
        $set = [];
        $type = null;

        foreach ($viewables as $viewable) {
            if (! $viewable instanceof Viewable) {
                throw InvalidViewable::classDoesNotImplementViewable(get_debug_type($viewable));
            }

            $key = ViewableKey::of($viewable) ?? throw InvalidViewable::missingKey($viewable::class);
            $type ??= $viewable->getMorphClass();

            if ($viewable->getMorphClass() !== $type) {
                throw InvalidViewable::mixedTypes($type, $viewable->getMorphClass());
            }

            $set[$key] ??= $viewable;
        }

        return new self($set);
    }

    /**
     * Stands for the type of the set, null when it is empty.
     */
    public function type(): ?Viewable
    {
        return array_first($this->viewables) ?? null;
    }

    /**
     * In the order they were given, the first of each key.
     *
     * @return array<int|string, Viewable>
     */
    public function all(): array
    {
        return $this->viewables;
    }

    /**
     * Sorted, so the database walks its index in order.
     *
     * @return list<int|string>
     */
    public function keys(): array
    {
        $keys = array_keys($this->viewables);
        $integers = array_filter($keys, is_int(...)) === $keys;

        sort($keys, $integers ? SORT_NUMERIC : SORT_STRING);

        return $keys;
    }
}
