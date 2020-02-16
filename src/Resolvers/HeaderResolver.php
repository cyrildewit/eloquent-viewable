<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Resolvers;

use Illuminate\Support\Facades\Request;
use CyrildeWit\EloquentViewable\Contracts\HeaderResolver as HeaderResolverContract;

class HeaderResolver implements HeaderResolverContract
{
    /**
     * Resolve the header.
     *
     * @return mixed
     */
    public function resolve(string $name)
    {
        return Request::header($name);
    }
}
