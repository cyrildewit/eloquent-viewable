<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Recording\Events;

use CyrildeWit\EloquentViewable\Contracts\Viewable;

/**
 * Dispatched once the store has deleted the views of a viewable, from
 * `destroy()` and from deleting a model that removes its views. A viewable
 * without a key stands for its whole type.
 */
class ViewsDestroyed
{
    public function __construct(public Viewable $viewable) {}
}
