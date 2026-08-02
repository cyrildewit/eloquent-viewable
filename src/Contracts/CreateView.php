<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Contracts;

use CyrildeWit\EloquentViewable\PendingView;

interface CreateView
{
    public function handle(PendingView $pending): View;
}
