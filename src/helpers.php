<?php

use CyrildeWit\EloquentViewable\Contracts\Viewable;
use CyrildeWit\EloquentViewable\Contracts\Views;
use Illuminate\Container\Container;

if (! function_exists('views')) {
    /**
     * Construct a new views instance.
     *
     * @param  \CyrildeWit\EloquentViewable\Contracts\Viewable|string
     * @return \CyrildeWit\EloquentViewable\Contracts\Views
     */
    function views($viewable): Views
    {
        $builder = Container::getInstance()->make(Views::class);

        if (is_string($viewable)) {
            $model = Container::getInstance()->make($viewable);

            if ($model instanceof Viewable) {
                $viewable = $model;
            }
        }

        return $builder->forViewable($viewable);
    }
}
