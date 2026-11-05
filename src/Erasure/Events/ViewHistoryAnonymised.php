<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Erasure\Events;

use CyrildeWit\EloquentViewable\Erasure\Subject;

class ViewHistoryAnonymised
{
    public function __construct(
        public Subject $subject,
        public int $views,
    ) {}
}
