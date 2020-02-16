<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Contracts;

use CyrildeWit\EloquentViewable\Contracts\Viewable as ViewableContract;
use CyrildeWit\EloquentViewable\Support\Period;

interface Views
{
    /**
     * Set the viewable model.
     *
     * @param  \CyrildeWit\EloquentViewable\Contracts\Viewable|null
     * @return $this
     */
    public function forViewable(ViewableContract $viewable = null): self;

    /**
     * Count the views.
     *
     * @return int
     */
    public function count(): int;

    /**
     * Record a view.
     *
     * @return \CyrildeWit\EloquentViewable\Contracts\View|void
     */
    public function record();

    /**
     * Destroy all views of the viewable model.
     *
     * @return void
     */
    public function destroy();

    /**
     * Set a cooldown.
     *
     * @param  \DateTime|int  $cooldown
     * @return $this
     */
    public function cooldown($cooldown): self;

    /**
     * Set the period.
     *
     * @param  \CyrildeWit\EloquentViewable\Period
     * @return $this
     */
    public function period($period): self;

    /**
     * Set the collection.
     *
     * @param  string
     * @return $this
     */
    public function collection(string $name): self;

    /**
     * Fetch only unique views.
     *
     * @param  bool  $state
     * @return $this
     */
    public function unique(bool $state = true): self;

    /**
     * Cache the current views count.
     *
     * @param  \DateTime|int|null  $lifetime
     * @return $this
     */
    public function remember($lifetime = null): self;

    /**
     * Override the visitor's IP Address.
     *
     * @param  string  $address
     * @return $this
     */
    public function useIpAddress(string $address): self;

    /**
     * Override the visitor's unique ID.
     *
     * @param  string  $visitor
     * @return $this
     */
    public function useVisitor(string $visitor): self;
}
