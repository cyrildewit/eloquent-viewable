<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Events;

use CyrildeWit\EloquentViewable\Contracts\View;
use Illuminate\Queue\SerializesModels;

class ViewRecorded
{
    use SerializesModels;

    public View $view;

    public function __construct(View $view)
    {
        $this->view = $view;
    }
}
