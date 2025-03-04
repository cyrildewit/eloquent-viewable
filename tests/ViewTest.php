<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Tests;

use Carbon\Carbon;
use CyrildeWit\EloquentViewable\Support\Period;
use CyrildeWit\EloquentViewable\Tests\TestClasses\Models\Post;
use CyrildeWit\EloquentViewable\View;
use Illuminate\Container\Container;

class ViewTest extends TestCase
{
    /** @test */
    public function it_can_have_a_custom_connection_through_config_file()
    {
        Container::getInstance()->make('config')->get(['eloquent-viewable.models.view.connection', 'sqlite']);

        $this->assertEquals('sqlite', (new View)->getConnection()->getName());
    }

    /** @test */
    public function it_can_fill_visitor()
    {
        $view = new View([
            'visitor' => 'uniqueString',
        ]);

        $this->assertEquals('uniqueString', $view->getAttribute('visitor'));
    }

    /** @test */
    public function it_can_fill_visitor_with_null()
    {
        $view = new View([
            'visitor' => null,
        ]);

        $this->assertNull($view->getAttribute('visitor'));
    }

    public function it_can_fill_collection()
    {
        $view = new View([
            'collection' => null,
        ]);

        $this->assertNull($view->getAttribute('collection'));
    }

    /** @test */
    public function it_can_fill_viewed_at()
    {
        Carbon::setTestNow($now = Carbon::create(2018, 1, 12));

        $view = new View([
            'viewed_at' => $now,
        ]);

        $this->assertEquals('2018-01-12', $view->viewed_at->format('Y-m-d'));
    }

    /** @test */
    public function it_can_belong_to_viewable_model()
    {
        $post = Post::factory()->create();

        View::create([
            'viewable_id' => $post->getKey(),
            'viewable_type' => $post->getMorphClass(),
        ]);

        $this->assertInstanceOf(Post::class, View::first()->viewable);
    }

    /** @test */
    public function it_can_scope_to_within_period_with_only_start_date_time()
    {
        $post = Post::factory()->create();

        $this->assertEquals(
            'select * from "views" where "viewed_at" >= ?',
            View::withinPeriod(Period::since('2019-06-12'))->toSql()
        );
    }

    /** @test */
    public function it_can_scope_to_within_period_with_only_end_date_time()
    {
        $post = Post::factory()->create();

        $this->assertEquals(
            'select * from "views" where "viewed_at" <= ?',
            View::withinPeriod(Period::upto('2019-03-23'))->toSql()
        );
    }

    /** @test */
    public function it_can_scope_to_within_period_with_both_start_and_end_date_time()
    {
        $post = Post::factory()->create();

        $this->assertEquals(
            'select * from "views" where "viewed_at" between ? and ?',
            View::withinPeriod(Period::create('2019-02-15', '2019-06-12'))->toSql()
        );
    }

    /** @test */
    public function it_can_scope_to_collection_null()
    {
        $post = Post::factory()->create();

        $this->assertEquals(
            'select * from "views" where "collection" is null',
            View::collection(null)->toSql()
        );
    }

    /** @test */
    public function it_can_scope_to_collection_custom()
    {
        $post = Post::factory()->create();

        $this->assertEquals(
            'select * from "views" where "collection" = ?',
            View::collection('custom')->toSql()
        );
    }
}
