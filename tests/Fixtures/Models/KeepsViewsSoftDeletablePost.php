<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Tests\Fixtures\Models;

class KeepsViewsSoftDeletablePost extends SoftDeletablePost
{
    #[\Override]
    public function shouldRemoveViewsOnDelete(): bool
    {
        return false;
    }
}
