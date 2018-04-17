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

Route::middleware(['web'])->group(function () {
    $cookieName = config('eloquent-viewable.cookie_name', 'eloquent_viewable');

    if (Cookie::get($cookieName) == false) {
        Cookie::queue($cookieName, str_random(80), 2628000);
    }
});
