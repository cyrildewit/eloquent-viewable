<?php

use CyrildeWit\EloquentViewable\Views;

if (! function_exists('views')) {
    function views($subject = null)
    {
        return app(Views::class)->setSubject($subject);
    }
}
