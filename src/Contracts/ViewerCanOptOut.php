<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Contracts;

/**
 * Implement this on the viewer model to let a person turn recording off,
 * such as from a "don't record my reading history" account setting. The
 * `IgnoreOptedOutViewers` guard honours it.
 */
interface ViewerCanOptOut
{
    /**
     * Whether the views this model makes may be recorded.
     */
    public function tracksViews(): bool;
}
