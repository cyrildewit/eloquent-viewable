<?php

use CyrildeWit\EloquentViewable\Views;
use Illuminate\Database\Eloquent\Model;

if (! function_exists('views')) {
    function views(Model $subject)
    {
        return app(Views::class)->setSubject($subject);
    }
}
