<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Recording\Events;

use CyrildeWit\EloquentViewable\Contracts\Viewable;

class ViewsDestroyed
{
    public function __construct(public Viewable $viewable) {}
}
