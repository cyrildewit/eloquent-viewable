<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Tests\Fixtures\Models;

use CyrildeWit\EloquentViewable\Concerns\InteractsWithViews;
use CyrildeWit\EloquentViewable\Contracts\Viewable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $title
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class UuidPost extends Model implements Viewable
{
    use HasUuids, InteractsWithViews;

    #[\Override]
    protected $table = 'uuid_posts';

    #[\Override]
    protected $guarded = [];
}
