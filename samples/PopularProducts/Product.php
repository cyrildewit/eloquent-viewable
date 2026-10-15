<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Samples\PopularProducts;

use CyrildeWit\EloquentViewable\Concerns\InteractsWithViews;
use CyrildeWit\EloquentViewable\Contracts\Viewable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $name
 * @property int $views_count Kept up to date by CountProductView.
 * @property int|null $recorded_views Set when the product is loaded through RecountProductViews.
 */
class Product extends Model implements Viewable
{
    use InteractsWithViews;

    #[\Override]
    protected $guarded = [];

    #[\Override]
    protected $casts = [
        'views_count' => 'integer',
    ];

    /**
     * Sort on the stored counter. Unlike `orderByViews()`, this does not
     * count the views table, so it costs the same however many views there
     * are.
     *
     * @param  Builder<self>  $query
     */
    public function scopeMostViewed(Builder $query): void
    {
        $query->orderByDesc('views_count')->orderByDesc('id');
    }
}
