<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Tests\Fixtures\Models;

use CyrildeWit\EloquentViewable\Concerns\InteractsWithViews;
use CyrildeWit\EloquentViewable\Contracts\Viewable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class SoftDeletablePost extends Model implements Viewable
{
    use InteractsWithViews;
    use SoftDeletes;

    #[\Override]
    protected $table = 'posts';

    #[\Override]
    protected $guarded = [];
}
