<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Visitors\Contracts;

use Illuminate\Database\Eloquent\Model;

/**
 * What the request says about the visitor. Every method reports a fact; the
 * recording guards turn those facts into a decision.
 */
interface Visitor
{
    public function id(): string;

    /**
     * The signed-in model, or null for a guest.
     */
    public function viewer(): ?Model;

    public function ip(): ?string;

    public function userAgent(): ?string;

    /**
     * Whether the request sends `DNT: 1`.
     */
    public function hasDoNotTrackHeader(): bool;

    /**
     * Whether the request sends `Sec-GPC: 1`, the Global Privacy Control
     * signal.
     */
    public function hasGlobalPrivacyControl(): bool;
}
