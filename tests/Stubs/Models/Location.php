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

namespace CyrildeWit\EloquentViewable\Tests\Stubs\Models;

use Illuminate\Database\Eloquent\Model;
use CyrildeWit\EloquentViewable\Traits\Viewable;

/**
 * Location class.
 *
 * @author Cyril de Wit <github@cyrildewit.nl>
 */
class Location extends Model
{
    use Viewable;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'locations';
    /**
     * The attributes that aren't mass assignable.
     *
     * @var array
     */
    protected $guarded = [];
}
