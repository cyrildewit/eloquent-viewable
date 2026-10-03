<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Recording\Events;

use CyrildeWit\EloquentViewable\Contracts\Viewable;

/** Dispatched once the views are deleted. A viewable without a key stands for its whole type. */
class ViewsDestroyed
{
    public function __construct(public Viewable $viewable) {}
}
