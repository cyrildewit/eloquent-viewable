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

namespace CyrildeWit\EloquentViewable\Support;

use Request;

/**
 * Class Ip.
 *
 * @deprecated 3.0.0 This class will be removed.
 * @author Cyril de Wit <github@cyrildewit.nl>
 */
class IpAddress
{
    /**
     * Get the visitor's ip address.
     *
     * @return bool
     */
    public function get()
    {
        return Request::ip();
    }
}
