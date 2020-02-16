<?php

use CyrildeWit\EloquentViewable\Contracts\Viewable as ViewableContract;
use CyrildeWit\EloquentViewable\Views;

if (! function_exists('views')) {
    function views($viewable = null)
    {
        $builder = app(Views::class);

        if (is_string($viewable)) {
            $model = app($viewable);

            if ($model instanceof ViewableContract) {
                $viewable = $model;
            }
        }

        return $builder->forViewable($viewable);
    }
}
