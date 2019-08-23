<?php

declare(strict_types=1);

/*
 * This file is part of the Eloquent Viewable package.
 *
 * (c) Cyril de Wit <github@cyrildewit.nl>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace CyrildeWit\EloquentViewable\Contracts;

use Illuminate\Database\Eloquent\Builder;
use CyrildeWit\EloquentViewable\Support\Period;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use CyrildeWit\EloquentViewable\Contracts\Viewable as ViewableContract;

interface Views
{
    /**
     * Set the viewable model.
     *
     * @param  \CyrildeWit\EloquentViewable\Contracts\Viewable|null
     * @return $this
     */
    public function forViewable(ViewableContract $viewable = null): Views;

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
     * Set the delay in the session.
     *
     * @param  \DateTime|int  $delay
     * @return $this
     */
    public function delayInSession($delay): Views;

    /**
     * Set the period.
     *
     * @param  \CyrildeWit\EloquentViewable\Period
     * @return $this
     */
    public function period($period): Views;

    /**
     * Set the collection.
     *
     * @param  string
     * @return $this
     */
    public function collection(string $name): Views;

    /**
     * Fetch only unique views.
     *
     * @param  bool  $state
     * @return $this
     */
    public function unique(bool $state = true): Views;

    /**
     * Cache the current views count.
     *
     * @param  \DateTime|int|null  $lifetime
     * @return $this
     */
    public function remember($lifetime = null): Views;

    /**
     * Override the visitor's IP Address.
     *
     * @param  string  $address
     * @return $this
     */
    public function useIpAddress(string $address): Views;

    /**
     * Override the visitor's unique ID.
     *
     * @param  string  $visitor
     * @return $this
     */
    public function useVisitor(string $visitor): Views;
}
