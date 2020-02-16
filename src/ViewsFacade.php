<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable;

use Illuminate\Support\Facades\Facade;
use CyrildeWit\EloquentViewable\Contracts\Views as ViewsContract;

/**
 * @see \CyrildeWit\EloquentViewable\Views
 * @codeCoverageIgnore
 */
class ViewsFacade extends Facade
{
    /**
     * Get the registered name of the component.
     *
     * @return string
     */
    protected static function getFacadeAccessor()
    {
        return ViewsContract::class;
    }
}
