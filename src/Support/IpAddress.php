<?php

declare(strict_types=1);

/*
 * This file is part of Eloquent Viewable.
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
