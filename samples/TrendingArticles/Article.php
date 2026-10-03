<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Samples\TrendingArticles;

use CyrildeWit\EloquentViewable\Concerns\InteractsWithViews;
use CyrildeWit\EloquentViewable\Contracts\Viewable;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $title
 * @property int|null $views_count Set when the article is loaded through TrendingArticles.
 */
class Article extends Model implements Viewable
{
    use InteractsWithViews;

    #[\Override]
    protected $guarded = [];
}
