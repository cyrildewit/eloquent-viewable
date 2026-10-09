<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Tests\Fixtures\Models;

use CyrildeWit\EloquentViewable\Models\View;
use Illuminate\Database\Eloquent\SoftDeletes;

class SoftDeletableView extends View
{
    use SoftDeletes;
}
