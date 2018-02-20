<?php

declare(strict_types=1);

/*
 * This file is part of Eloquent Visitable.
 *
 * (c) Cyril de Wit <github@cyrildewit.nl>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace CyrildeWit\EloquentVisitable\Tests\Stubs\Models;

use Illuminate\Database\Eloquent\Model;
use CyrildeWit\EloquentVisitable\Traits\Visitable;

/**
 * This is the Post Eloquent model class.
 *
 * @author Cyril de Wit <github@cyrildewit.nl>
 */
class Post extends Model
{
    use Visitable;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'posts';
    /**
     * The attributes that aren't mass assignable.
     *
     * @var array
     */
    protected $guarded = [];
}
