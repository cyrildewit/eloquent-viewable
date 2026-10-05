<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Erasure\Events;

use CyrildeWit\EloquentViewable\Erasure\Subject;

/** Dispatched when the view history of the subject is exported. */
class ViewHistoryExported
{
    public function __construct(public Subject $subject) {}
}
