<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Events;

use CyrildeWit\EloquentViewable\Contracts\View;
use Illuminate\Queue\SerializesModels;

class ViewRecorded
{
    use SerializesModels;

    public function __construct(public View $view) {}
}
