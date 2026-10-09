<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Samples\RecentlyViewed;

use CyrildeWit\EloquentViewable\Concerns\HasViewHistory;
use Illuminate\Foundation\Auth\User as Authenticatable;

/**
 * The model people sign in as, the `User` of most applications.
 *
 * @property int $id
 * @property string $name
 */
class Learner extends Authenticatable
{
    use HasViewHistory;

    #[\Override]
    protected $guarded = [];
}
