<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Resolvers;

use CyrildeWit\EloquentViewable\Contracts\HeaderResolver as HeaderResolverContract;
use Illuminate\Support\Facades\Request;

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
