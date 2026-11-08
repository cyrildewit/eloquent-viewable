<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Tests\Fixtures\Models;

class PropertyRemovesViewsPost extends Post
{
    #[\Override]
    protected $table = 'posts';

    protected bool $removeViewsOnDelete = true;
}
