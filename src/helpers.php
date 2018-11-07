<?php

use CyrildeWit\EloquentViewable\Views;
use Illuminate\Database\Eloquent\Model;

if (! function_exists('views')) {
    function views($subject = null)
    {
        return app(Views::class)->setSubject($subject);
    }
}
