<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Erasure\Events;

use CyrildeWit\EloquentViewable\Erasure\Subject;

/** It is dispatched when the export starts, before the returned collection reads a view. */
class ViewHistoryExported
{
    public function __construct(public Subject $subject) {}
}
