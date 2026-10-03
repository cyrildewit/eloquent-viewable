<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Tests\Fixtures\Models;

use CyrildeWit\EloquentViewable\Concerns\InteractsWithViews;
use CyrildeWit\EloquentViewable\Contracts\Viewable;
use CyrildeWit\EloquentViewable\Tests\Fixtures\Models\Factories\PostFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $title
 * @property string $body
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class Post extends Model implements Viewable
{
    /** @use HasFactory<PostFactory> */
    use HasFactory;

    use InteractsWithViews;

    #[\Override]
    protected $guarded = [];

    protected static function newFactory(): PostFactory
    {
        return PostFactory::new();
    }
}
