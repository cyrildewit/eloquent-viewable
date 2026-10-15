<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Benchmarks\Support;

/**
 * One variant of a benchmark subject: the subject method with one parameter
 * set, named the way phpbench names it in its dump.
 */
final readonly class Variant
{
    /**
     * The parameter set name as phpbench reports it: the provider keys joined
     * with a comma and no space, or an empty string for a subject without
     * providers.
     */
    public string $set;

    /**
     * @param  class-string  $class
     * @param  list<string>  $keys  the key each provider yielded, in provider order
     * @param  array<string, mixed>  $params  the merged parameters of the variant
     */
    public function __construct(
        public string $class,
        public string $subject,
        public array $keys,
        public array $params,
    ) {
        $this->set = implode(',', $keys);
    }

    /**
     * The class name without its namespace.
     */
    public function benchmark(): string
    {
        return substr($this->class, strrpos($this->class, '\\') + 1);
    }

    /**
     * A heading for the console: `CountViewsBench::benchCount (hot article, all time)`.
     */
    public function title(): string
    {
        $title = $this->benchmark().'::'.$this->subject;

        return $this->keys === [] ? $title : $title.' ('.implode(', ', $this->keys).')';
    }
}
