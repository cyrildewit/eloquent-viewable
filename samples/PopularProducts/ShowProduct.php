<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Samples\PopularProducts;

class ShowProduct
{
    /**
     * A shopper who browses back and forth between products is counted once
     * per hour for each of them.
     */
    private const int COOLDOWN_MINUTES = 60;

    public function __invoke(Product $product): Product
    {
        // The crawler check and the cooldown run here, during the request.
        // Only the insert, and the counter update that follows it, move to
        // the queue.
        views($product)
            ->cooldown(self::COOLDOWN_MINUTES)
            ->queue()
            ->record();

        return $product;
    }
}
