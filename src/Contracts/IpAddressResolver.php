<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Contracts;

interface IpAddressResolver
{
    /**
     * Resolve the IP address.
     *
     * @return string
     */
    public function resolve(): string;
}
