<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable;

use CyrildeWit\EloquentViewable\Contracts\Views as ViewsContract;
use Illuminate\Support\Facades\Facade;

/**
 * @see Views
 *
 * @codeCoverageIgnore
 */
class ViewsFacade extends Facade
{
    /**
     * Get the registered name of the component.
     */
    protected static function getFacadeAccessor(): string
    {
        return ViewsContract::class;
    }
}
