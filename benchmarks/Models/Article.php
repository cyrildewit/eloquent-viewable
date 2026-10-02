<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Benchmarks\Models;

use CyrildeWit\EloquentViewable\Concerns\InteractsWithViews;
use CyrildeWit\EloquentViewable\Contracts\Viewable;
use Illuminate\Database\Eloquent\Model;

/**
 * The viewable that carries nine out of ten seeded views.
 */
class Article extends Model implements Viewable
{
    use InteractsWithViews;

    #[\Override]
    protected $guarded = [];

    #[\Override]
    public $timestamps = false;
}
