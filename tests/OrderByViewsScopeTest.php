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

namespace CyrildeWit\EloquentViewable\Tests;

use Carbon\Carbon;
use CyrildeWit\EloquentViewable\Support\Period;
use CyrildeWit\EloquentViewable\OrderByViewsScope;
use CyrildeWit\EloquentViewable\Enums\SortDirection;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use CyrildeWit\EloquentViewable\Tests\TestClasses\Models\Post;

class OrderByViewsScopeTest extends TestCase
{
    /** @var \CyrildeWit\EloquentViewable\Tests\TestClasses\Models\Post */
    protected $post;

    public function setUp(): void
    {
        parent::setUp();

        $this->post = factory(Post::class)->create();
    }

    /** @test */
    public function it_builds_a_query_with_no_options()
    {
        $query = (new OrderByViewsScope())->apply(Post::query());

        $this->assertEquals(
            'select posts.*, count(visitor) as views_count from "posts" left join "views" on "views"."viewable_id" = "posts"."id" and "views"."viewable_type" = ? group by "posts"."id" order by "views_count" asc',
            $query->toSql()
        );
    }

    /** @test */
    public function it_builds_a_query_with_option_unique()
    {
        $query = (new OrderByViewsScope())->apply(Post::query(), [
            'unique' => true,
        ]);

        $this->assertEquals(
            'select posts.*, count(distinct visitor) as views_count from "posts" left join "views" on "views"."viewable_id" = "posts"."id" and "views"."viewable_type" = ? group by "posts"."id" order by "views_count" asc',
            $query->toSql()
        );
    }

    /** @test */
    public function it_builds_a_query_with_option_direction()
    {
        $query = (new OrderByViewsScope())->apply(Post::query(), [
            'descending' => true,
        ]);

        $this->assertEquals(
            'select posts.*, count(visitor) as views_count from "posts" left join "views" on "views"."viewable_id" = "posts"."id" and "views"."viewable_type" = ? group by "posts"."id" order by "views_count" desc',
            $query->toSql()
        );
    }

    /** @test */
    public function it_builds_a_query_with_option_period_with_only_startDateTime()
    {
        $query = (new OrderByViewsScope())->apply(Post::query(), [
            'period' => Period::since('2019-06-12'),
        ]);

        $this->assertEquals(
            'select posts.*, count(visitor) as views_count from "posts" left join "views" on "views"."viewable_id" = "posts"."id" and "views"."viewable_type" = ? where "views"."viewed_at" >= ? group by "posts"."id" order by "views_count" asc',
            $query->toSql()
        );
    }

    /** @test */
    public function it_builds_a_query_with_option_period_with_only_endDateTime()
    {
        $query = (new OrderByViewsScope())->apply(Post::query(), [
            'period' => Period::upto('2019-03-23'),
        ]);

        $this->assertEquals(
            'select posts.*, count(visitor) as views_count from "posts" left join "views" on "views"."viewable_id" = "posts"."id" and "views"."viewable_type" = ? where "views"."viewed_at" <= ? group by "posts"."id" order by "views_count" asc',
            $query->toSql()
        );
    }

    /** @test */
    public function it_builds_a_query_with_option_period_with_both_startDateTime_and_endDateTime()
    {
        $query = (new OrderByViewsScope())->apply(Post::query(), [
            'period' => Period::create('2019-02-15', '2019-06-12'),
        ]);

        $this->assertEquals(
            'select posts.*, count(visitor) as views_count from "posts" left join "views" on "views"."viewable_id" = "posts"."id" and "views"."viewable_type" = ? where "views"."viewed_at" between ? and ? group by "posts"."id" order by "views_count" asc',
            $query->toSql()
        );
    }

    /** @test */
    public function it_builds_a_query_with_option_collection()
    {
        $query = (new OrderByViewsScope())->apply(Post::query(), [
            'collection' => 'custom-collection',
        ]);

        $this->assertEquals(
            'select posts.*, count(visitor) as views_count from "posts" left join "views" on "views"."viewable_id" = "posts"."id" and "views"."viewable_type" = ? and "views"."collection" = ? group by "posts"."id" order by "views_count" asc',
            $query->toSql()
        );
    }
}
