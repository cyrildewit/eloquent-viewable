<?php

declare(strict_types=1);

use CyrildeWit\EloquentViewable\Contracts\CreateView;
use CyrildeWit\EloquentViewable\Events\ViewRecorded;
use CyrildeWit\EloquentViewable\Jobs\StoreView;
use CyrildeWit\EloquentViewable\Samples\PopularProducts\CountProductView;
use CyrildeWit\EloquentViewable\Samples\PopularProducts\Product;
use CyrildeWit\EloquentViewable\Samples\PopularProducts\RecountProductViews;
use CyrildeWit\EloquentViewable\Samples\PopularProducts\ShowProduct;
use CyrildeWit\EloquentViewable\Tests\TestClasses\Models\Post;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Route;

beforeEach(function (): void {
    // The web middleware encrypts the session and visitor cookies.
    config(['app.key' => 'base64:'.base64_encode(random_bytes(32))]);

    Route::get('/products/{product}', ShowProduct::class)->middleware('web');

    Event::listen(ViewRecorded::class, CountProductView::class);
});

/**
 * Opens the product page `$count` times, each time as a different shopper.
 * The test queue is synchronous, so each view is stored and counted before
 * the request returns.
 */
function viewProduct(Product $product, int $count = 1): void
{
    for ($i = 0; $i < $count; $i++) {
        session()->flush();

        test()->get("/products/{$product->id}")->assertOk();
    }
}

it('keeps a running count of the views on the product', function (): void {
    $product = Product::create(['name' => 'Desk lamp']);

    viewProduct($product, 3);

    expect($product->fresh()->views_count)->toBe(3)
        ->and($product)->toHaveViewsCount(3);
});

it('sorts the catalog on the counter, most viewed first', function (): void {
    $lamp = Product::create(['name' => 'Desk lamp']);
    $chair = Product::create(['name' => 'Chair']);
    $rug = Product::create(['name' => 'Rug']);

    viewProduct($lamp);
    viewProduct($chair, 3);

    expect(Product::query()->mostViewed()->pluck('name')->all())->toBe(['Chair', 'Desk lamp', 'Rug']);
});

it('counts the view once the queued job has stored it', function (): void {
    Queue::fake();
    $product = Product::create(['name' => 'Desk lamp']);

    viewProduct($product);

    expect($product->fresh()->views_count)->toBe(0);

    Queue::assertPushed(StoreView::class, 1);
    Queue::pushed(StoreView::class)->first()->handle(app(CreateView::class));

    expect($product->fresh()->views_count)->toBe(1);
});

it('does not queue a view for a shopper who comes back within the cooldown', function (): void {
    Queue::fake();
    $product = Product::create(['name' => 'Desk lamp']);

    $this->get("/products/{$product->id}")->assertOk();
    $this->withCookie(config('session.cookie'), session()->getId())
        ->get("/products/{$product->id}")
        ->assertOk();

    Queue::assertPushed(StoreView::class, 1);
});

it('does not treat a view as an edit of the product', function (): void {
    $product = Product::create(['name' => 'Desk lamp']);
    $this->travel(1)->day();

    viewProduct($product);

    expect($product->fresh()->updated_at->equalTo($product->updated_at))->toBeTrue();
});

it('leaves out views of other models and views in a collection', function (): void {
    $product = Product::create(['name' => 'Desk lamp']);
    $post = Post::create(['title' => 'Lighting guide', 'body' => '']);

    views($product)->collection('compare')->record();
    views($post)->record();

    expect($product->fresh()->views_count)->toBe(0);
});

it('recounts the counters that drifted from the views table', function (): void {
    $lamp = Product::create(['name' => 'Desk lamp']);
    $chair = Product::create(['name' => 'Chair']);

    viewProduct($lamp, 2);
    viewProduct($chair);
    views($chair)->collection('compare')->record();
    $lamp->views()->first()->delete();
    Product::query()->whereKey($chair->id)->update(['views_count' => 40]);

    expect(app(RecountProductViews::class)())->toBe(2)
        ->and($lamp->fresh()->views_count)->toBe(1)
        ->and($chair->fresh()->views_count)->toBe(1);
});
