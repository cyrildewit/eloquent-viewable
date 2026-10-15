<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Facades;

use CyrildeWit\EloquentViewable\Testing\ViewsFake;
use CyrildeWit\EloquentViewable\Views as ViewsBuilder;
use Illuminate\Container\Container;
use Illuminate\Support\Facades\Facade;

/** @see ViewsBuilder */
class Views extends Facade
{
    #[\Override]
    protected static $cached = false;

    public static function fake(): ViewsFake
    {
        return ViewsFake::bind(static::getFacadeApplication() ?? Container::getInstance());
    }

    /** @codeCoverageIgnore */
    protected static function getFacadeAccessor(): string
    {
        return ViewsBuilder::class;
    }
}
