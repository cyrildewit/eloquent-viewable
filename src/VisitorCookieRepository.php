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
    public function __construct()
    {
        $this->request = app(Request::class);
        $this->key = config('eloquent-viewable.visitor_cookie_key');
    }

    public function get()
    {
        $this->request->cookie($this->key);
    }

    public function generate()
    {
        if (! $this->get()) {

        }
    }
}
