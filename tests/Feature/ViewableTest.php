<?php

declare(strict_types=1);

use Carbon\Carbon;
use CyrildeWit\EloquentViewable\Support\Period;
use CyrildeWit\EloquentViewable\Tests\TestClasses\Models\Factories\ViewFactory;
use CyrildeWit\EloquentViewable\Tests\TestClasses\Models\Post;
use Illuminate\Database\Eloquent\Relations\MorphMany;

beforeEach(function (): void {
    $this->post = Post::factory()->create();
});

it('has a views relationship', function (): void {
    expect($this->post->views())->toBeInstanceOf(MorphMany::class);
});

it('can be ordered by views in descending order', function (): void {
    $postOne = $this->post;
    $postTwo = Post::factory()->create();
    $postThree = Post::factory()->create();
    $postFour = Post::factory()->create();

    ViewFactory::new()->for($postOne, 'viewable')->count(4)->create();

    ViewFactory::new()->for($postTwo, 'viewable')->create();

    ViewFactory::new()->for($postThree, 'viewable')->count(2)->create();

    ViewFactory::new()->for($postFour, 'viewable')->count(3)->create();

    expect(Post::orderByViews()->pluck('id'))->toEqual(collect([1, 4, 3, 2]));
});

it('can be ordered by unique views in descending order', function (): void {
    $postOne = $this->post;
    $postTwo = Post::factory()->create();
    $postThree = Post::factory()->create();
    $postFour = Post::factory()->create();

    // Unique views: 3
    ViewFactory::new()->for($postOne, 'viewable')->fromVisitor('visitor_one')->count(2)->create();
    ViewFactory::new()->for($postOne, 'viewable')->fromVisitor('visitor_two')->create();
    ViewFactory::new()->for($postOne, 'viewable')->fromVisitor('visitor_three')->create();

    // Unique views: 2
    ViewFactory::new()->for($postTwo, 'viewable')->fromVisitor('visitor_one')->create();
    ViewFactory::new()->for($postTwo, 'viewable')->fromVisitor('visitor_two')->count(2)->create();

    // Unique views: 4
    ViewFactory::new()->for($postThree, 'viewable')->fromVisitor('visitor_one')->count(2)->create();
    ViewFactory::new()->for($postThree, 'viewable')->fromVisitor('visitor_two')->create();
    ViewFactory::new()->for($postThree, 'viewable')->fromVisitor('visitor_three')->create();
    ViewFactory::new()->for($postThree, 'viewable')->fromVisitor('visitor_four')->create();

    // Unique views: 1
    ViewFactory::new()->for($postFour, 'viewable')->fromVisitor('visitor_one')->count(2)->create();

    expect(Post::orderByUniqueViews()->pluck('id'))->toEqual(collect([3, 1, 2, 4]));
});

it('can be ordered by views within a specific period in descending order', function (): void {
    Carbon::setTestNow(Carbon::now());

    $postOne = $this->post;
    $postTwo = Post::factory()->create();
    $postThree = Post::factory()->create();
    $postFour = Post::factory()->create();

    // Views within period: 3
    ViewFactory::new()->for($postOne, 'viewable')->viewedAt(Carbon::now())->create();
    ViewFactory::new()->for($postOne, 'viewable')->viewedAt(Carbon::now()->subDays(2))->create();
    ViewFactory::new()->for($postOne, 'viewable')->viewedAt(Carbon::now()->subDays(8))->create();
    ViewFactory::new()->for($postOne, 'viewable')->viewedAt(Carbon::now()->subDays(13))->create();

    // Views within period: 1
    ViewFactory::new()->for($postTwo, 'viewable')->viewedAt(Carbon::now())->create();
    ViewFactory::new()->for($postTwo, 'viewable')->viewedAt(Carbon::now()->subDays(13))->create();

    // Views within period: 2
    ViewFactory::new()->for($postThree, 'viewable')->viewedAt(Carbon::now())->create();
    ViewFactory::new()->for($postThree, 'viewable')->viewedAt(Carbon::now()->subDays(8))->create();
    ViewFactory::new()->for($postThree, 'viewable')->viewedAt(Carbon::now()->subDays(13))->create();

    // Views within period: 4
    ViewFactory::new()->for($postFour, 'viewable')->viewedAt(Carbon::now())->create();
    ViewFactory::new()->for($postFour, 'viewable')->viewedAt(Carbon::now()->subDays(3))->create();
    ViewFactory::new()->for($postFour, 'viewable')->viewedAt(Carbon::now()->subDays(4))->create();
    ViewFactory::new()->for($postFour, 'viewable')->viewedAt(Carbon::now()->subDays(7))->create();

    expect(Post::orderByViews('desc', Period::pastDays(10))->pluck('id'))->toEqual(collect([4, 1, 3, 2]));
});

it('can be ordered by views in a specific collection descending', function (): void {
    $postOne = $this->post;
    $postTwo = Post::factory()->create();
    $postThree = Post::factory()->create();
    $postFour = Post::factory()->create();

    // Views in collection: 0
    ViewFactory::new()->for($postOne, 'viewable')->inCollection('wrong_collection')->count(2)->create();
    ViewFactory::new()->for($postOne, 'viewable')->create();

    // Views in collection: 2
    ViewFactory::new()->for($postTwo, 'viewable')->inCollection('good_collection')->count(2)->create();
    ViewFactory::new()->for($postTwo, 'viewable')->create();

    // Views in collection: 3
    ViewFactory::new()->for($postThree, 'viewable')->inCollection('good_collection')->count(3)->create();
    ViewFactory::new()->for($postThree, 'viewable')->inCollection('wrong_collection')->create();
    ViewFactory::new()->for($postThree, 'viewable')->create();

    // Views in collection: 1
    ViewFactory::new()->for($postFour, 'viewable')->inCollection('good_collection')->create();
    ViewFactory::new()->for($postFour, 'viewable')->create();

    expect(Post::orderByViews('desc', null, 'good_collection')->pluck('id'))->toEqual(collect([3, 2, 4, 1]));
});

it('can be ordered by views in a specific collection ascending', function (): void {
    $postOne = $this->post;
    $postTwo = Post::factory()->create();
    $postThree = Post::factory()->create();
    $postFour = Post::factory()->create();

    // Views in collection: 0
    ViewFactory::new()->for($postOne, 'viewable')->inCollection('wrong_collection')->count(2)->create();
    ViewFactory::new()->for($postOne, 'viewable')->create();

    // Views in collection: 2
    ViewFactory::new()->for($postTwo, 'viewable')->inCollection('good_collection')->count(2)->create();
    ViewFactory::new()->for($postTwo, 'viewable')->create();

    // Views in collection: 3
    ViewFactory::new()->for($postThree, 'viewable')->inCollection('good_collection')->count(3)->create();
    ViewFactory::new()->for($postThree, 'viewable')->inCollection('wrong_collection')->create();
    ViewFactory::new()->for($postThree, 'viewable')->create();

    // Views in collection: 1
    ViewFactory::new()->for($postFour, 'viewable')->inCollection('good_collection')->create();
    ViewFactory::new()->for($postFour, 'viewable')->create();

    expect(Post::orderByViews('asc', null, 'good_collection')->pluck('id'))->toEqual(collect([1, 4, 2, 3]));
});

it('can be ordered by views in ascending order', function (): void {
    $postOne = $this->post;
    $postTwo = Post::factory()->create();
    $postThree = Post::factory()->create();
    $postFour = Post::factory()->create();

    ViewFactory::new()->for($postOne, 'viewable')->count(4)->create();

    ViewFactory::new()->for($postTwo, 'viewable')->create();

    ViewFactory::new()->for($postThree, 'viewable')->count(2)->create();

    ViewFactory::new()->for($postFour, 'viewable')->count(3)->create();

    expect(Post::orderByViews('asc')->pluck('id'))->toEqual(collect([2, 3, 4, 1]));
});

it('can be ordered by unique views in ascending order', function (): void {
    $postOne = $this->post;
    $postTwo = Post::factory()->create();
    $postThree = Post::factory()->create();
    $postFour = Post::factory()->create();

    // Unique views: 3
    ViewFactory::new()->for($postOne, 'viewable')->fromVisitor('visitor_one')->count(2)->create();
    ViewFactory::new()->for($postOne, 'viewable')->fromVisitor('visitor_two')->create();
    ViewFactory::new()->for($postOne, 'viewable')->fromVisitor('visitor_three')->create();

    // Unique views: 2
    ViewFactory::new()->for($postTwo, 'viewable')->fromVisitor('visitor_one')->create();
    ViewFactory::new()->for($postTwo, 'viewable')->fromVisitor('visitor_two')->count(2)->create();

    // Unique views: 4
    ViewFactory::new()->for($postThree, 'viewable')->fromVisitor('visitor_one')->count(2)->create();
    ViewFactory::new()->for($postThree, 'viewable')->fromVisitor('visitor_two')->create();
    ViewFactory::new()->for($postThree, 'viewable')->fromVisitor('visitor_three')->create();
    ViewFactory::new()->for($postThree, 'viewable')->fromVisitor('visitor_four')->create();

    // Unique views: 1
    ViewFactory::new()->for($postFour, 'viewable')->fromVisitor('visitor_one')->count(2)->create();

    expect(Post::orderByUniqueViews('asc')->pluck('id'))->toEqual(collect([4, 2, 1, 3]));
});

it('can be ordered by unique views within a specific period in ascending order', function (): void {
    Carbon::setTestNow(Carbon::now());

    $postOne = $this->post;
    $postTwo = Post::factory()->create();
    $postThree = Post::factory()->create();
    $postFour = Post::factory()->create();

    // Views within period: 3
    ViewFactory::new()
        ->for($postOne, 'viewable')
        ->fromVisitor('visitor_one')
        ->viewedAt(Carbon::now())
        ->count(2)
        ->create();
    ViewFactory::new()
        ->for($postOne, 'viewable')
        ->fromVisitor('visitor_two')
        ->viewedAt(Carbon::now()->subDays(2))
        ->count(2)
        ->create();
    ViewFactory::new()
        ->for($postOne, 'viewable')
        ->fromVisitor('visitor_three')
        ->viewedAt(Carbon::now()->subDays(8))
        ->create();
    ViewFactory::new()
        ->for($postOne, 'viewable')
        ->fromVisitor('visitor_four')
        ->viewedAt(Carbon::now()->subDays(13))
        ->create();

    // Views within period: 1
    ViewFactory::new()
        ->for($postTwo, 'viewable')
        ->fromVisitor('visitor_one')
        ->viewedAt(Carbon::now())
        ->create();
    ViewFactory::new()
        ->for($postTwo, 'viewable')
        ->fromVisitor('visitor_two')
        ->viewedAt(Carbon::now()->subDays(13))
        ->count(2)
        ->create();

    // Views within period: 2
    ViewFactory::new()
        ->for($postThree, 'viewable')
        ->fromVisitor('visitor_one')
        ->viewedAt(Carbon::now())
        ->create();
    ViewFactory::new()
        ->for($postThree, 'viewable')
        ->fromVisitor('visitor_two')
        ->viewedAt(Carbon::now()->subDays(8))
        ->create();
    ViewFactory::new()
        ->for($postThree, 'viewable')
        ->fromVisitor('visitor_three')
        ->viewedAt(Carbon::now()->subDays(13))
        ->count(2)
        ->create();

    // Views within period: 4
    ViewFactory::new()
        ->for($postFour, 'viewable')
        ->fromVisitor('visitor_one')
        ->viewedAt(Carbon::now())
        ->count(2)
        ->create();
    ViewFactory::new()
        ->for($postFour, 'viewable')
        ->fromVisitor('visitor_two')
        ->viewedAt(Carbon::now()->subDays(3))
        ->create();
    ViewFactory::new()
        ->for($postFour, 'viewable')
        ->fromVisitor('visitor_three')
        ->viewedAt(Carbon::now()->subDays(4))
        ->create();
    ViewFactory::new()
        ->for($postFour, 'viewable')
        ->fromVisitor('visitor_four')
        ->viewedAt(Carbon::now()->subDays(7))
        ->create();

    expect(Post::orderByUniqueViews('asc', Period::pastDays(10))->pluck('id'))->toEqual(collect([2, 3, 1, 4]));
});
