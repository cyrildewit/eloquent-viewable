<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Benchmarks\Support;

use CyrildeWit\EloquentViewable\Visitors\Contracts\Visitor;
use Illuminate\Database\Eloquent\Model;

/**
 * A visitor every recording guard lets through, so a recording benchmark
 * measures the write and not the request that would normally carry the cookie.
 */
final readonly class BenchmarkVisitor implements Visitor
{
    public function __construct(private string $id) {}

    public function id(): string
    {
        return $this->id;
    }

    public function viewer(): ?Model
    {
        return null;
    }

    public function ip(): string
    {
        return '203.0.113.10';
    }

    /**
     * A desktop browser, which the crawler guard lets through.
     */
    public function userAgent(): string
    {
        return 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/129.0 Safari/537.36';
    }

    public function hasDoNotTrackHeader(): bool
    {
        return false;
    }

    public function hasGlobalPrivacyControl(): bool
    {
        return false;
    }

    public function isPrefetch(): bool
    {
        return false;
    }

    public function isHeadRequest(): bool
    {
        return false;
    }
}
