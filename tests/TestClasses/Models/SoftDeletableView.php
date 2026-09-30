<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Tests\TestClasses\Models;

use CyrildeWit\EloquentViewable\View;
use Illuminate\Database\Eloquent\SoftDeletes;

class SoftDeletableView extends View
{
    use SoftDeletes;
}
