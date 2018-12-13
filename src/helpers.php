<?php

use CyrildeWit\EloquentViewable\Views;

if (! function_exists('views')) {
    function views($viewable = null)
    {
        return app(Views::class)->forViewable($viewable);
    }
}
