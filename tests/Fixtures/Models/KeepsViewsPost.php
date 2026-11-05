<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Tests\Fixtures\Models;

class KeepsViewsPost extends Post
{
    #[\Override]
    protected $table = 'posts';

    #[\Override]
    public function shouldRemoveViewsOnDelete(): bool
    {
        return false;
    }
}
