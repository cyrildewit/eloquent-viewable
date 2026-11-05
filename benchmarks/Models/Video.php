<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Benchmarks\Models;

use CyrildeWit\EloquentViewable\Concerns\InteractsWithViews;
use CyrildeWit\EloquentViewable\Contracts\Viewable;
use Illuminate\Database\Eloquent\Model;

/**
 * A second viewable type, so every query has to filter on `viewable_type`
 * the way it does in an application with more than one viewable model.
 */
class Video extends Model implements Viewable
{
    use InteractsWithViews;

    #[\Override]
    protected $guarded = [];

    #[\Override]
    public $timestamps = false;
}
