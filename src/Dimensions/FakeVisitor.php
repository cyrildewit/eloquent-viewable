<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Dimensions;

use CyrildeWit\EloquentViewable\Visitors\Contracts\Visitor;
use Illuminate\Database\Eloquent\Model;

/**
 * The visitor behind `DimensionInput::fake()`: it reports what the test hands
 * it and sends none of the privacy signals.
 *
 * @internal
 */
final readonly class FakeVisitor implements Visitor
{
    public function __construct(
        private ?string $userAgent = null,
        private ?string $ip = null,
        private ?Model $viewer = null,
    ) {}

    public function id(): string
    {
        return 'fake-visitor';
    }

    public function viewer(): ?Model
    {
        return $this->viewer;
    }

    public function ip(): ?string
    {
        return $this->ip;
    }

    public function userAgent(): ?string
    {
        return $this->userAgent;
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
