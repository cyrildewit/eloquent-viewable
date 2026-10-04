<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Samples\ContentDashboard;

use CyrildeWit\EloquentViewable\Concerns\InteractsWithViews;
use CyrildeWit\EloquentViewable\Contracts\Viewable;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $title
 */
class Guide extends Model implements Viewable
{
    use InteractsWithViews;

    #[\Override]
    protected $guarded = [];
}
