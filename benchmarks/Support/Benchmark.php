<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Benchmarks\Support;

/**
 * A benchmark class as phpbench sees it: its groups, the methods it runs
 * before a subject and every variant of every subject.
 */
final readonly class Benchmark
{
    /**
     * @param  class-string  $class
     * @param  list<string>  $groups
     * @param  list<string>  $beforeMethods
     * @param  list<Variant>  $variants
     */
    public function __construct(
        public string $class,
        public array $groups,
        public array $beforeMethods,
        public array $variants,
    ) {}

    /**
     * The class name without its namespace.
     */
    public function name(): string
    {
        return substr($this->class, strrpos($this->class, '\\') + 1);
    }

    public function inGroup(string $group): bool
    {
        return in_array($group, $this->groups, true);
    }
}
