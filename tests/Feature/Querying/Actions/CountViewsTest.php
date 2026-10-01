<?php

declare(strict_types=1);

use Carbon\Carbon;
use CyrildeWit\EloquentViewable\Querying\Actions\CountViews;
use CyrildeWit\EloquentViewable\Querying\Contracts\CountsViews as CountsViewsContract;
use CyrildeWit\EloquentViewable\Support\Period;
use CyrildeWit\EloquentViewable\Support\ViewsQuery;
use CyrildeWit\EloquentViewable\Tests\TestClasses\Models\Factories\ViewFactory;
use CyrildeWit\EloquentViewable\Tests\TestClasses\Models\Post;
use Illuminate\Container\Container;

function countViews(): CountsViewsContract
{
    return Container::getInstance()->make(CountsViewsContract::class);
}

beforeEach(function (): void {
    $this->post = Post::factory()->create();
});

it('is bound to the CountViews contract', function (): void {
    expect(countViews())->toBeInstanceOf(CountViews::class);
});

it('counts the views of a viewable', function (): void {
    ViewFactory::new()->for($this->post, 'viewable')->count(2)->create();
    ViewFactory::new()->for(Post::factory()->create(), 'viewable')->create();

    expect(countViews()->handle($this->post, new ViewsQuery))->toBe(2);
});

it('counts the views of a viewable type', function (): void {
    ViewFactory::new()->for($this->post, 'viewable')->create();
    ViewFactory::new()->for(Post::factory()->create(), 'viewable')->create();

    expect(countViews()->handle(new Post, new ViewsQuery))->toBe(2);
});

it('counts unique visitors', function (): void {
    ViewFactory::new()->for($this->post, 'viewable')->fromVisitor('visitor_one')->count(2)->create();
    ViewFactory::new()->for($this->post, 'viewable')->fromVisitor('visitor_two')->create();

    expect(countViews()->handle($this->post, new ViewsQuery(unique: true)))->toBe(2);
});

it('applies the period and collection', function (): void {
    ViewFactory::new()->for($this->post, 'viewable')->inCollection('custom')->viewedAt(Carbon::parse('2026-01-10'))->create();
    ViewFactory::new()->for($this->post, 'viewable')->inCollection('custom')->viewedAt(Carbon::parse('2026-02-10'))->create();
    ViewFactory::new()->for($this->post, 'viewable')->viewedAt(Carbon::parse('2026-02-10'))->create();

    expect(countViews()->handle($this->post, new ViewsQuery(Period::since('2026-02-01'), 'custom')))->toBe(1);
});
