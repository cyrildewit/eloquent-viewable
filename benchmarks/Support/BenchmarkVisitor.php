<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Benchmarks\Support;

use CyrildeWit\EloquentViewable\Contracts\Visitor;

/**
 * A visitor that is never filtered out, so a recording benchmark measures
 * the write and not the request that would normally carry the cookie.
 */
final readonly class BenchmarkVisitor implements Visitor
{
    public function __construct(private string $id) {}

    public function id(): string
    {
        return $this->id;
    }

    public function ip(): string
    {
        return '203.0.113.10';
    }

    public function hasDoNotTrackHeader(): bool
    {
        return false;
    }

    public function isCrawler(): bool
    {
        return false;
    }
}
