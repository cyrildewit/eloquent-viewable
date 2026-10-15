<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Erasure\Events;

use CyrildeWit\EloquentViewable\Erasure\Subject;

/** It is dispatched once the views of the subject are deleted, also when it had none. */
class ViewHistoryForgotten
{
    public function __construct(
        public Subject $subject,
        public int $views,
    ) {}
}
