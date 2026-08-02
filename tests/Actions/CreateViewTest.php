<?php

declare(strict_types=1);

use Carbon\Carbon;
use CyrildeWit\EloquentViewable\Actions\CreateView;
use CyrildeWit\EloquentViewable\Contracts\CreateView as CreateViewContract;
use CyrildeWit\EloquentViewable\Events\ViewRecorded;
use CyrildeWit\EloquentViewable\PendingView;
use CyrildeWit\EloquentViewable\Tests\TestClasses\Models\Post;
use CyrildeWit\EloquentViewable\View;
use Illuminate\Container\Container;
use Illuminate\Support\Facades\Event;

beforeEach(function (): void {
    $this->post = Post::factory()->create();
});

it('is bound to the CreateView contract', function (): void {
    expect(Container::getInstance()->make(CreateViewContract::class))->toBeInstanceOf(CreateView::class);
});

it('stores a pending view', function (): void {
    $pending = new PendingView(
        viewableId: $this->post->getKey(),
        viewableType: $this->post->getMorphClass(),
        visitor: 'visitor_one',
        collection: 'custom',
        viewedAt: Carbon::now(),
    );

    $view = Container::getInstance()->make(CreateViewContract::class)->handle($pending);

    expect(View::count())->toBe(1)
        ->and($view->viewable_id)->toBe($this->post->getKey())
        ->and($view->visitor)->toBe('visitor_one')
        ->and($view->collection)->toBe('custom');
});

it('dispatches a ViewRecorded event', function (): void {
    Event::fake();

    $pending = new PendingView(
        viewableId: $this->post->getKey(),
        viewableType: $this->post->getMorphClass(),
        visitor: 'visitor_one',
        collection: null,
        viewedAt: Carbon::now(),
    );

    Container::getInstance()->make(CreateViewContract::class)->handle($pending);

    Event::assertDispatched(ViewRecorded::class);
});
