<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Events;

use Illuminate\Queue\SerializesModels;

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
    public function __construct(Viewable $view)
    {
        $this->view = $view;
    }
}
