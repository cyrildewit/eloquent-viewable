<?php

declare(strict_types=1);

/*
 * This file is part of the Eloquent Viewable package.
 *
 * (c) Cyril de Wit <github@cyrildewit.nl>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace CyrildeWit\EloquentViewable\Resolvers;

use Illuminate\Support\Facades\Request;
use CyrildeWit\EloquentViewable\Contracts\IpAddressResolver as IpAddressResolverContract;

class IpAddressResolver implements IpAddressResolverContract
{
    /**
     * {@inheritdoc}
     */
    public function resolve(): string
    {
        return Request::ip();
    }
}
