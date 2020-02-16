<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Tests\TestClasses\Models;

use CyrildeWit\EloquentViewable\Contracts\Viewable as ViewableContract;
use CyrildeWit\EloquentViewable\Viewable;
use Illuminate\Database\Eloquent\Model;

class Apartment extends Model implements ViewableContract
{
    use Viewable;

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
