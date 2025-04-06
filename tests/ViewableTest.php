<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Tests;

use Carbon\Carbon;
use CyrildeWit\EloquentViewable\Support\Period;
use CyrildeWit\EloquentViewable\Tests\TestClasses\Models\Post;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class ViewableTest extends TestCase
{
    protected Post $post;

    protected function setUp(): void
    {
        parent::setUp();

        $this->post = Post::factory()->create();
    }

    public function test_it_has_a_views_relationship(): void
    {
        $this->assertInstanceOf(MorphMany::class, $this->post->views());
    }

    public function test_it_can_be_ordered_by_views_in_descending_order(): void
    {
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

        $this->assertEquals(collect([1, 4, 3, 2]), Post::orderByViews()->pluck('id'));
    }

    public function test_it_can_be_ordered_by_unique_views_in_descending_order(): void
    {
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

        $this->assertEquals(collect([3, 1, 2, 4]), Post::orderByUniqueViews()->pluck('id'));
    }

    public function test_it_can_be_ordered_by_views_within_a_specific_period_in_descending_order(): void
    {
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

        $this->assertEquals(collect([4, 1, 3, 2]), Post::orderByViews('desc', Period::pastDays(10))->pluck('id'));
    }

    public function test_it_can_be_ordered_by_views_in_a_specific_collection_descending(): void
    {
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

        $this->assertEquals(collect([3, 2, 4, 1]), Post::orderByViews('desc', null, 'good_collection')->pluck('id'));
    }

    public function test_it_can_be_ordered_by_views_in_a_specific_collection_ascending(): void
    {
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

        $this->assertEquals(collect([1, 4, 2, 3]), Post::orderByViews('asc', null, 'good_collection')->pluck('id'));
    }

    public function test_it_can_be_ordered_by_views_in_ascending_order(): void
    {
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

        $this->assertEquals(collect([2, 3, 4, 1]), Post::orderByViews('asc')->pluck('id'));
    }

    public function test_it_can_be_ordered_by_unique_views_in_ascending_order(): void
    {
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

        $this->assertEquals(collect([4, 2, 1, 3]), Post::orderByUniqueViews('asc')->pluck('id'));
    }

    public function test_it_can_be_ordered_by_unique_views_within_a_specific_period_in_ascending_order(): void
    {
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

        $this->assertEquals(collect([2, 3, 1, 4]), Post::orderByUniqueViews('asc', Period::pastDays(10))->pluck('id'));
    }
}
