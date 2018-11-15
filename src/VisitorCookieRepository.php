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

namespace CyrildeWit\EloquentViewable;

use Cookie;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Database\Eloquent\Model;

class VisitorCookieRepository
{
    /**
     * The request instance.
     *
     * @var \Illuminate\Contracts\Session\Repository
     */
    protected $request;

    protected $key;

    /**
     * Create a new view session history instance.
     */
    public function __construct(Request $request)
    {
        $this->request = $request;
        $this->key = config('eloquent-viewable.visitor_cookie_key');
    }

    public function get()
    {
        if (! Cookie::has($this->key)) {
            Cookie::queue($this->key, $uniqueString = $this->generateUniqueString(), $this->expirationInMinutes());

            return $uniqueString;
        }

        return Cookie::get($this->key);
    }

    private function generateUniqueString()
    {
        return str_random(80);
    }

    private function expirationInMinutes()
    {
        return 2628000; // aka 5 years
    }
}
