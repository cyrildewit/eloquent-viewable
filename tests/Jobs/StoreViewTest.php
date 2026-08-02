<?php

declare(strict_types=1);

use Carbon\Carbon;
use CyrildeWit\EloquentViewable\Contracts\CreateView as CreateViewContract;
use CyrildeWit\EloquentViewable\Jobs\StoreView;
use CyrildeWit\EloquentViewable\PendingView;
use CyrildeWit\EloquentViewable\Tests\TestClasses\Models\Post;
use CyrildeWit\EloquentViewable\View;
use Illuminate\Container\Container;

it('stores the pending view through the create view action', function (): void {
    $post = Post::factory()->create();

    $pending = new PendingView(
        viewableId: $post->getKey(),
        viewableType: $post->getMorphClass(),
        visitor: 'visitor_one',
        collection: null,
        viewedAt: Carbon::now(),
    );

    new StoreView($pending)->handle(
        Container::getInstance()->make(CreateViewContract::class)
    );

    expect(View::count())->toBe(1);
});
