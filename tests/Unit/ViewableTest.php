<?php

declare(strict_types=1);

/*
 * This file is part of the Eloquent Viewable package.
 *
 * (c) Cyril de Wit <github@cyrildewit.nl>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace CyrildeWit\EloquentViewable\Tests\Unit;

use Carbon\Carbon;
use CyrildeWit\EloquentViewable\View;
use CyrildeWit\EloquentViewable\Support\Period;
use CyrildeWit\EloquentViewable\Tests\TestCase;
use CyrildeWit\EloquentViewable\Tests\TestHelper;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use CyrildeWit\EloquentViewable\Tests\Stubs\Models\Post;

class ViewableTest extends TestCase
{
    /** @var \CyrildeWit\EloquentViewable\Tests\Stubs\Models\Post */
    protected $post;

    public function setUp()
    {
        parent::setUp();

        $this->post = factory(Post::class)->create();
    }

    /** @test */
    public function it_has_a_views_relationship()
    {
        $this->assertInstanceOf(MorphMany::class, $this->post->views());
    }

    /** @test */
    public function it_can_be_ordered_by_views_count_in_descending_order()
    {
        $postOne = $this->post;
        $postTwo = factory(Post::class)->create();
        $postThree = factory(Post::class)->create();

        TestHelper::createNewView($postOne);
        TestHelper::createNewView($postOne);
        TestHelper::createNewView($postOne);

        TestHelper::createNewView($postTwo);

        TestHelper::createNewView($postThree);
        TestHelper::createNewView($postThree);

        $this->assertEquals(collect([1, 3, 2]), Post::orderByViews()->pluck('id'));
    }

    /** @test */
    public function it_can_be_ordered_by_views_count_in_ascending_order()
    {
        $postOne = $this->post;
        $postTwo = factory(Post::class)->create();
        $postThree = factory(Post::class)->create();

        TestHelper::createNewView($postOne);
        TestHelper::createNewView($postOne);
        TestHelper::createNewView($postOne);

        TestHelper::createNewView($postTwo);

        TestHelper::createNewView($postThree);
        TestHelper::createNewView($postThree);

        $this->assertEquals(collect([2, 3, 1]), Post::orderByViews('asc')->pluck('id'));
    }

    /** @test */
    public function it_can_be_ordered_by_unique_views_count_in_descending_order()
    {
        $postOne = $this->post;
        $postTwo = factory(Post::class)->create();
        $postThree = factory(Post::class)->create();

        TestHelper::createNewView($postOne, ['visitor' => 'visitor_one']);
        TestHelper::createNewView($postOne, ['visitor' => 'visitor_two']);
        TestHelper::createNewView($postOne, ['visitor' => 'visitor_three']);

        TestHelper::createNewView($postTwo, ['visitor' => 'visitor_one']);

        TestHelper::createNewView($postThree, ['visitor' => 'visitor_one']);
        TestHelper::createNewView($postThree, ['visitor' => 'visitor_two']);

        $this->assertEquals(collect([1, 3, 2]), Post::orderByUniqueViews()->pluck('id'));
    }

    /** @test */
    public function it_can_be_ordered_by_unique_views_count_in_ascending_order()
    {
        $postOne = $this->post;
        $postTwo = factory(Post::class)->create();
        $postThree = factory(Post::class)->create();

        TestHelper::createNewView($postOne, ['visitor' => 'visitor_one']);
        TestHelper::createNewView($postOne, ['visitor' => 'visitor_two']);
        TestHelper::createNewView($postOne, ['visitor' => 'visitor_three']);

        TestHelper::createNewView($postTwo, ['visitor' => 'visitor_one']);

        TestHelper::createNewView($postThree, ['visitor' => 'visitor_one']);
        TestHelper::createNewView($postThree, ['visitor' => 'visitor_two']);

        $this->assertEquals(collect([2, 3, 1]), Post::orderByUniqueViews('asc')->pluck('id'));
    }

    /** @test */
    public function it_can_be_ordered_by_views_count_with_a_specific_period_in_descending_order()
    {
        Carbon::setTestNow(Carbon::now());

        $postOne = $this->post;
        $postTwo = factory(Post::class)->create();
        $postThree = factory(Post::class)->create();

        TestHelper::createNewView($postOne, ['viewed_at' => Carbon::now()]);
        TestHelper::createNewView($postOne, ['viewed_at' => Carbon::now()->subDays(2)]);
        TestHelper::createNewView($postOne, ['viewed_at' => Carbon::now()->subDays(8)]);
        TestHelper::createNewView($postOne, ['viewed_at' => Carbon::now()->subDays(13)]);

        TestHelper::createNewView($postTwo, ['viewed_at' => Carbon::now()]);
        TestHelper::createNewView($postTwo, ['viewed_at' => Carbon::now()->subDays(13)]);

        TestHelper::createNewView($postThree, ['viewed_at' => Carbon::now()]);
        TestHelper::createNewView($postThree, ['viewed_at' => Carbon::now()->subDays(8)]);
        TestHelper::createNewView($postThree, ['viewed_at' => Carbon::now()->subDays(13)]);

        $this->assertEquals(collect([1, 3, 2]), Post::orderByViews('desc', Period::pastDays(10))->pluck('id'));
    }

    /** @test */
    public function it_can_be_ordered_by_views_count_with_a_specific_period_in_ascending_order()
    {
        Carbon::setTestNow(Carbon::now());

        $postOne = $this->post;
        $postTwo = factory(Post::class)->create();
        $postThree = factory(Post::class)->create();

        TestHelper::createNewView($postOne, ['viewed_at' => Carbon::now()]);
        TestHelper::createNewView($postOne, ['viewed_at' => Carbon::now()->subDays(2)]);
        TestHelper::createNewView($postOne, ['viewed_at' => Carbon::now()->subDays(8)]);
        TestHelper::createNewView($postOne, ['viewed_at' => Carbon::now()->subDays(13)]);

        TestHelper::createNewView($postTwo, ['viewed_at' => Carbon::now()]);
        TestHelper::createNewView($postTwo, ['viewed_at' => Carbon::now()->subDays(13)]);

        TestHelper::createNewView($postThree, ['viewed_at' => Carbon::now()]);
        TestHelper::createNewView($postThree, ['viewed_at' => Carbon::now()->subDays(8)]);
        TestHelper::createNewView($postThree, ['viewed_at' => Carbon::now()->subDays(13)]);

        $this->assertEquals(collect([2, 3, 1]), Post::orderByViews('asc', Period::pastDays(10))->pluck('id'));
    }

    /** @test */
    public function it_can_be_ordered_by_unique_views_count_with_a_specific_period_in_ascending_order()
    {
        Carbon::setTestNow(Carbon::now());

        $postOne = $this->post;
        $postTwo = factory(Post::class)->create();
        $postThree = factory(Post::class)->create();

        TestHelper::createNewView($postOne, ['viewed_at' => Carbon::now(), 'visitor' => 'visitor_one']);
        TestHelper::createNewView($postOne, ['viewed_at' => Carbon::now()->subDays(2), 'visitor' => 'visitor_one']);
        TestHelper::createNewView($postOne, ['viewed_at' => Carbon::now()->subDays(8), 'visitor' => 'visitor_two']);
        TestHelper::createNewView($postOne, ['viewed_at' => Carbon::now()->subDays(13), 'visitor' => 'visitor_three']);

        TestHelper::createNewView($postTwo, ['viewed_at' => Carbon::now(), 'visitor' => 'visitor_one']);
        TestHelper::createNewView($postTwo, ['viewed_at' => Carbon::now()->subDays(5), 'visitor' => 'visitor_two']);
        TestHelper::createNewView($postTwo, ['viewed_at' => Carbon::now()->subDays(13), 'visitor' => 'visitor_two']);

        TestHelper::createNewView($postThree, ['viewed_at' => Carbon::now(), 'visitor' => 'visitor_one']);
        TestHelper::createNewView($postThree, ['viewed_at' => Carbon::now()->subDays(14), 'visitor' => 'visitor_one']);
        TestHelper::createNewView($postThree, ['viewed_at' => Carbon::now()->subDays(18), 'visitor' => 'visitor_one']);

        $this->assertEquals(collect([3, 2, 1]), Post::orderByUniqueViews('asc', Period::pastDays(10))->pluck('id'));
    }
}
