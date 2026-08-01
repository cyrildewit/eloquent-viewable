<?php

declare(strict_types=1);

use Carbon\Carbon;
use CyrildeWit\EloquentViewable\Support\Period;
use CyrildeWit\EloquentViewable\Tests\TestClasses\Models\Post;
use CyrildeWit\EloquentViewable\Tests\TestHelper;
use Illuminate\Database\Eloquent\Relations\MorphMany;

beforeEach(function () {
    $this->post = Post::factory()->create();
});

it('has a views relationship', function () {
    expect($this->post->views())->toBeInstanceOf(MorphMany::class);
});

it('can be ordered by views in descending order', function () {
    $postOne = $this->post;
    $postTwo = Post::factory()->create();
    $postThree = Post::factory()->create();
    $postFour = Post::factory()->create();

    TestHelper::createView($postOne);
    TestHelper::createView($postOne);
    TestHelper::createView($postOne);
    TestHelper::createView($postOne);

    TestHelper::createView($postTwo);

    TestHelper::createView($postThree);
    TestHelper::createView($postThree);

    TestHelper::createView($postFour);
    TestHelper::createView($postFour);
    TestHelper::createView($postFour);

    expect(Post::orderByViews()->pluck('id'))->toEqual(collect([1, 4, 3, 2]));
});

it('can be ordered by unique views in descending order', function () {
    $postOne = $this->post;
    $postTwo = Post::factory()->create();
    $postThree = Post::factory()->create();
    $postFour = Post::factory()->create();

    // Unique views: 3
    TestHelper::createView($postOne, ['visitor' => 'visitor_one']);
    TestHelper::createView($postOne, ['visitor' => 'visitor_one']);
    TestHelper::createView($postOne, ['visitor' => 'visitor_two']);
    TestHelper::createView($postOne, ['visitor' => 'visitor_three']);

    // Unique views: 2
    TestHelper::createView($postTwo, ['visitor' => 'visitor_one']);
    TestHelper::createView($postTwo, ['visitor' => 'visitor_two']);
    TestHelper::createView($postTwo, ['visitor' => 'visitor_two']);

    // Unique views: 4
    TestHelper::createView($postThree, ['visitor' => 'visitor_one']);
    TestHelper::createView($postThree, ['visitor' => 'visitor_one']);
    TestHelper::createView($postThree, ['visitor' => 'visitor_two']);
    TestHelper::createView($postThree, ['visitor' => 'visitor_three']);
    TestHelper::createView($postThree, ['visitor' => 'visitor_four']);

    // Unique views: 1
    TestHelper::createView($postFour, ['visitor' => 'visitor_one']);
    TestHelper::createView($postFour, ['visitor' => 'visitor_one']);

    expect(Post::orderByUniqueViews()->pluck('id'))->toEqual(collect([3, 1, 2, 4]));
});

it('can be ordered by views within a specific period in descending order', function () {
    Carbon::setTestNow(Carbon::now());

    $postOne = $this->post;
    $postTwo = Post::factory()->create();
    $postThree = Post::factory()->create();
    $postFour = Post::factory()->create();

    // Views within period: 3
    TestHelper::createView($postOne, ['viewed_at' => Carbon::now()]);
    TestHelper::createView($postOne, ['viewed_at' => Carbon::now()->subDays(2)]);
    TestHelper::createView($postOne, ['viewed_at' => Carbon::now()->subDays(8)]);
    TestHelper::createView($postOne, ['viewed_at' => Carbon::now()->subDays(13)]);

    // Views within period: 1
    TestHelper::createView($postTwo, ['viewed_at' => Carbon::now()]);
    TestHelper::createView($postTwo, ['viewed_at' => Carbon::now()->subDays(13)]);

    // Views within period: 2
    TestHelper::createView($postThree, ['viewed_at' => Carbon::now()]);
    TestHelper::createView($postThree, ['viewed_at' => Carbon::now()->subDays(8)]);
    TestHelper::createView($postThree, ['viewed_at' => Carbon::now()->subDays(13)]);

    // Views within period: 4
    TestHelper::createView($postFour, ['viewed_at' => Carbon::now()]);
    TestHelper::createView($postFour, ['viewed_at' => Carbon::now()->subDays(3)]);
    TestHelper::createView($postFour, ['viewed_at' => Carbon::now()->subDays(4)]);
    TestHelper::createView($postFour, ['viewed_at' => Carbon::now()->subDays(7)]);

    expect(Post::orderByViews('desc', Period::pastDays(10))->pluck('id'))->toEqual(collect([4, 1, 3, 2]));
});

it('can be ordered by views in a specific collection descending', function () {
    $postOne = $this->post;
    $postTwo = Post::factory()->create();
    $postThree = Post::factory()->create();
    $postFour = Post::factory()->create();

    // Views in collection: 0
    TestHelper::createView($postOne, ['collection' => 'wrong_collection']);
    TestHelper::createView($postOne, ['collection' => 'wrong_collection']);
    TestHelper::createView($postOne);

    // Views in collection: 2
    TestHelper::createView($postTwo, ['collection' => 'good_collection']);
    TestHelper::createView($postTwo, ['collection' => 'good_collection']);
    TestHelper::createView($postTwo);

    // Views in collection: 3
    TestHelper::createView($postThree, ['collection' => 'good_collection']);
    TestHelper::createView($postThree, ['collection' => 'good_collection']);
    TestHelper::createView($postThree, ['collection' => 'good_collection']);
    TestHelper::createView($postThree, ['collection' => 'wrong_collection']);
    TestHelper::createView($postThree);

    // Views in collection: 1
    TestHelper::createView($postFour, ['collection' => 'good_collection']);
    TestHelper::createView($postFour);

    expect(Post::orderByViews('desc', null, 'good_collection')->pluck('id'))->toEqual(collect([3, 2, 4, 1]));
});

it('can be ordered by views in a specific collection ascending', function () {
    $postOne = $this->post;
    $postTwo = Post::factory()->create();
    $postThree = Post::factory()->create();
    $postFour = Post::factory()->create();

    // Views in collection: 0
    TestHelper::createView($postOne, ['collection' => 'wrong_collection']);
    TestHelper::createView($postOne, ['collection' => 'wrong_collection']);
    TestHelper::createView($postOne);

    // Views in collection: 2
    TestHelper::createView($postTwo, ['collection' => 'good_collection']);
    TestHelper::createView($postTwo, ['collection' => 'good_collection']);
    TestHelper::createView($postTwo);

    // Views in collection: 3
    TestHelper::createView($postThree, ['collection' => 'good_collection']);
    TestHelper::createView($postThree, ['collection' => 'good_collection']);
    TestHelper::createView($postThree, ['collection' => 'good_collection']);
    TestHelper::createView($postThree, ['collection' => 'wrong_collection']);
    TestHelper::createView($postThree);

    // Views in collection: 1
    TestHelper::createView($postFour, ['collection' => 'good_collection']);
    TestHelper::createView($postFour);

    expect(Post::orderByViews('asc', null, 'good_collection')->pluck('id'))->toEqual(collect([1, 4, 2, 3]));
});

it('can be ordered by views in ascending order', function () {
    $postOne = $this->post;
    $postTwo = Post::factory()->create();
    $postThree = Post::factory()->create();
    $postFour = Post::factory()->create();

    TestHelper::createView($postOne);
    TestHelper::createView($postOne);
    TestHelper::createView($postOne);
    TestHelper::createView($postOne);

    TestHelper::createView($postTwo);

    TestHelper::createView($postThree);
    TestHelper::createView($postThree);

    TestHelper::createView($postFour);
    TestHelper::createView($postFour);
    TestHelper::createView($postFour);

    expect(Post::orderByViews('asc')->pluck('id'))->toEqual(collect([2, 3, 4, 1]));
});

it('can be ordered by unique views in ascending order', function () {
    $postOne = $this->post;
    $postTwo = Post::factory()->create();
    $postThree = Post::factory()->create();
    $postFour = Post::factory()->create();

    // Unique views: 3
    TestHelper::createView($postOne, ['visitor' => 'visitor_one']);
    TestHelper::createView($postOne, ['visitor' => 'visitor_one']);
    TestHelper::createView($postOne, ['visitor' => 'visitor_two']);
    TestHelper::createView($postOne, ['visitor' => 'visitor_three']);

    // Unique views: 2
    TestHelper::createView($postTwo, ['visitor' => 'visitor_one']);
    TestHelper::createView($postTwo, ['visitor' => 'visitor_two']);
    TestHelper::createView($postTwo, ['visitor' => 'visitor_two']);

    // Unique views: 4
    TestHelper::createView($postThree, ['visitor' => 'visitor_one']);
    TestHelper::createView($postThree, ['visitor' => 'visitor_one']);
    TestHelper::createView($postThree, ['visitor' => 'visitor_two']);
    TestHelper::createView($postThree, ['visitor' => 'visitor_three']);
    TestHelper::createView($postThree, ['visitor' => 'visitor_four']);

    // Unique views: 1
    TestHelper::createView($postFour, ['visitor' => 'visitor_one']);
    TestHelper::createView($postFour, ['visitor' => 'visitor_one']);

    expect(Post::orderByUniqueViews('asc')->pluck('id'))->toEqual(collect([4, 2, 1, 3]));
});

it('can be ordered by unique views within a specific period in ascending order', function () {
    Carbon::setTestNow(Carbon::now());

    $postOne = $this->post;
    $postTwo = Post::factory()->create();
    $postThree = Post::factory()->create();
    $postFour = Post::factory()->create();

    // Views within period: 3
    TestHelper::createView($postOne, ['visitor' => 'visitor_one', 'viewed_at' => Carbon::now()]);
    TestHelper::createView($postOne, ['visitor' => 'visitor_one', 'viewed_at' => Carbon::now()]);
    TestHelper::createView($postOne, ['visitor' => 'visitor_two', 'viewed_at' => Carbon::now()->subDays(2)]);
    TestHelper::createView($postOne, ['visitor' => 'visitor_two', 'viewed_at' => Carbon::now()->subDays(2)]);
    TestHelper::createView($postOne, ['visitor' => 'visitor_three', 'viewed_at' => Carbon::now()->subDays(8)]);
    TestHelper::createView($postOne, ['visitor' => 'visitor_four', 'viewed_at' => Carbon::now()->subDays(13)]);

    // Views within period: 1
    TestHelper::createView($postTwo, ['visitor' => 'visitor_one', 'viewed_at' => Carbon::now()]);
    TestHelper::createView($postTwo, ['visitor' => 'visitor_two', 'viewed_at' => Carbon::now()->subDays(13)]);
    TestHelper::createView($postTwo, ['visitor' => 'visitor_two', 'viewed_at' => Carbon::now()->subDays(13)]);

    // Views within period: 2
    TestHelper::createView($postThree, ['visitor' => 'visitor_one', 'viewed_at' => Carbon::now()]);
    TestHelper::createView($postThree, ['visitor' => 'visitor_two', 'viewed_at' => Carbon::now()->subDays(8)]);
    TestHelper::createView($postThree, ['visitor' => 'visitor_three', 'viewed_at' => Carbon::now()->subDays(13)]);
    TestHelper::createView($postThree, ['visitor' => 'visitor_three', 'viewed_at' => Carbon::now()->subDays(13)]);

    // Views within period: 4
    TestHelper::createView($postFour, ['visitor' => 'visitor_one', 'viewed_at' => Carbon::now()]);
    TestHelper::createView($postFour, ['visitor' => 'visitor_one', 'viewed_at' => Carbon::now()]);
    TestHelper::createView($postFour, ['visitor' => 'visitor_two', 'viewed_at' => Carbon::now()->subDays(3)]);
    TestHelper::createView($postFour, ['visitor' => 'visitor_three', 'viewed_at' => Carbon::now()->subDays(4)]);
    TestHelper::createView($postFour, ['visitor' => 'visitor_four', 'viewed_at' => Carbon::now()->subDays(7)]);

    expect(Post::orderByUniqueViews('asc', Period::pastDays(10))->pluck('id'))->toEqual(collect([2, 3, 1, 4]));
});
