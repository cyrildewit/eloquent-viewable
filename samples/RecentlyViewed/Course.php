<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Samples\RecentlyViewed;

use CyrildeWit\EloquentViewable\Concerns\InteractsWithViews;
use CyrildeWit\EloquentViewable\Contracts\Viewable;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $title
 */
class Course extends Model implements Viewable
{
    use InteractsWithViews;

    #[\Override]
    protected $guarded = [];
}
