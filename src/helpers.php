<?php

declare(strict_types=1);

use CyrildeWit\EloquentViewable\Contracts\Viewable;
use CyrildeWit\EloquentViewable\Contracts\Views;
use Illuminate\Container\Container;

// @codeCoverageIgnoreStart
if (! function_exists('views')) {
    // @codeCoverageIgnoreEnd
    function views(Viewable|string $viewable): Views
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
