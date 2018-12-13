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

class VisitorCookieRepository
{
    /**
     * The request instance.
     *
     * @var \Illuminate\Contracts\Session\Repository
     */
    protected $request;

    /**
     * The visitor cookie key.
     *
     * @var string
     */
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

    /**
     * Generate a unique visitor string.
     *
     * @return string
     */
    private function generateUniqueString(): string
    {
        return str_random(80);
    }

    /**
     * Get the expiration in minutes.
     *
     * @return int
     */
    private function expirationInMinutes(): int
    {
        return 2628000; // aka 5 years
    }
}
