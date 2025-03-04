<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Contracts;

use CyrildeWit\EloquentViewable\Contracts\Visitor as VisitorContract;
use CyrildeWit\EloquentViewable\Support\Period;
use DateTimeInterface;

interface Views
{
    /**
     * Set the viewable model.
     */
    public function forViewable(Viewable $viewable): self;

    /**
     * Get the views count.
     */
    public function count(): int;

    /**
     * Record a view.
     */
    public function record(): bool;

    /**
     * Destroy all views of the viewable model.
     */
    public function destroy(): void;

    /**
     * Set the cooldown.
     */
    public function cooldown(DateTimeInterface|int|null $cooldown): self;

    /**
     * Set the period.
     */
    public function period(?Period $period): self;

    /**
     * Set the collection.
     */
    public function collection(?string $name): self;

    /**
     * Fetch only unique views.
     */
    public function unique(bool $state = true): self;

    /**
     * Cache the current views count.
     */
    public function remember(DateTimeInterface|int|null $lifetime = null): self;

    /**
     * Set the visitor.
     */
    public function useVisitor(VisitorContract $visitor): self;
}
