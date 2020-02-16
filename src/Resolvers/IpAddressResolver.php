<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Resolvers;

use CyrildeWit\EloquentViewable\Contracts\IpAddressResolver as IpAddressResolverContract;
use Illuminate\Support\Facades\Request;

class IpAddressResolver implements IpAddressResolverContract
{
    /**
     * Resolve the IP address.
     *
     * @return string
     */
    public function resolve(): string
    {
        return Request::ip();
    }
}
