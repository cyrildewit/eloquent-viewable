<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Tests\TestClasses\Models;

use CyrildeWit\EloquentViewable\Contracts\Viewable;
use CyrildeWit\EloquentViewable\InteractsWithViews;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class SoftDeletablePost extends Model implements Viewable
{
    use InteractsWithViews, SoftDeletes;

    #[\Override]
    protected $table = 'posts';

    #[\Override]
    protected $guarded = [];
}
