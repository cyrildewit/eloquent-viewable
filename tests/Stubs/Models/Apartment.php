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

namespace CyrildeWit\EloquentViewable\Tests\Stubs\Models;

use Illuminate\Database\Eloquent\Model;
use CyrildeWit\EloquentViewable\HasViews;
use CyrildeWit\EloquentViewable\HasViewsTrait;

class Apartment extends Model // implements HasViews
{
    use HasViewsTrait;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'apartments';
    /**
     * The attributes that aren't mass assignable.
     *
     * @var array
     */
    protected $guarded = [];
}
