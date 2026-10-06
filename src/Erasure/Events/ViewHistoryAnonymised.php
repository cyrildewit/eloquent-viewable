<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Erasure\Events;

use CyrildeWit\EloquentViewable\Erasure\Subject;

/** It is dispatched once the views of the subject are anonymised, also when it had none. */
class ViewHistoryAnonymised
{
    public function __construct(
        public Subject $subject,
        public int $views,
    ) {}
}
