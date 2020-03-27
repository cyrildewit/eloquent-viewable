<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Events;

use Illuminate\Queue\SerializesModels;
use CyrildeWit\EloquentViewable\Contracts\View;

class ViewRecorded
{
    use SerializesModels;

    /**
     * The recorded view.
     *
     * @var \CyrildeWit\EloquentViewable\Contracts\View
     */
    public $view;

    /**
     * Create a new event instance.
     *
     * @param  \CyrildeWit\EloquentViewable\Contracts\View
     * @return void
     */
    public function __construct(View $view)
    {
        $this->view = $view;
    }
}
