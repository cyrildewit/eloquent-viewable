<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Samples\PopularProducts;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

class RecountProductViews
{
    private const int CHUNK_SIZE = 500;

    /**
     * Sets each counter back to the number of views in the views table, for
     * when the two have drifted apart: a job that failed after storing the
     * view, views removed with `destroy()`, or a counter added to a table
     * that already had views.
     *
     * @return int the number of products whose counter was off
     */
    public function __invoke(): int
    {
        $corrected = 0;

        Product::query()
            ->withCount(['views as recorded_views' => function (Builder $query): void {
                $query->whereNull('collection');
            }])
            ->chunkById(self::CHUNK_SIZE, function (Collection $products) use (&$corrected): void {
                /** @var Collection<int, Product> $products */
                foreach ($products as $product) {
                    $recorded = (int) $product->getAttribute('recorded_views');

                    if ($product->views_count === $recorded) {
                        continue;
                    }

                    Product::query()->whereKey($product->id)->toBase()->update(['views_count' => $recorded]);
                    $corrected++;
                }
            });

        return $corrected;
    }
}
