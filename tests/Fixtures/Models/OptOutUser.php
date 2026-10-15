<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Tests\Fixtures\Models;

use CyrildeWit\EloquentViewable\Contracts\ViewerCanOptOut;

class OptOutUser extends User implements ViewerCanOptOut
{
    #[\Override]
    protected $table = 'users';

    public bool $hidesReadingHistory = false;

    public function tracksViews(): bool
    {
        return ! $this->hidesReadingHistory;
    }
}
