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
    public function test_it_can_have_a_custom_connection_through_config_file(): void
    {
        Container::getInstance()->make('config')->get(['eloquent-viewable.models.view.connection', 'testing']);

        $this->assertEquals('testing', (new View)->getConnection()->getName());
    }

    public function test_it_can_fill_visitor(): void
    {
        $view = new View([
            'visitor' => 'uniqueString',
        ]);

        $this->assertEquals('uniqueString', $view->getAttribute('visitor'));
    }

    public function test_it_can_fill_visitor_with_null(): void
    {
        $view = new View([
            'visitor' => null,
        ]);

        $this->assertNull($view->getAttribute('visitor'));
    }

    public function test_it_can_fill_collection(): void
    {
        $view = new View([
            'collection' => null,
        ]);

        $this->assertNull($view->getAttribute('collection'));
    }

    public function test_it_can_fill_viewed_at(): void
    {
        Carbon::setTestNow($now = Carbon::create(2018, 1, 12));

        $view = new View([
            'viewed_at' => $now,
        ]);

        $this->assertEquals('2018-01-12', $view->viewed_at->format('Y-m-d'));
    }

    public function test_it_can_belong_to_viewable_model(): void
    {
        $post = Post::factory()->create();

        View::create([
            'viewable_id' => $post->getKey(),
            'viewable_type' => $post->getMorphClass(),
        ]);

        $this->assertInstanceOf(Post::class, View::first()->viewable);
    }

    public function test_it_can_scope_to_within_period_with_only_start_date_time(): void
    {
        Post::factory()->create();

        $this->assertEquals(
            'select * from "views" where "viewed_at" >= ?',
            View::withinPeriod(Period::since('2019-06-12'))->toSql()
        );
    }

    public function test_it_can_scope_to_within_period_with_only_end_date_time(): void
    {
        Post::factory()->create();

        $this->assertEquals(
            'select * from "views" where "viewed_at" <= ?',
            View::withinPeriod(Period::upto('2019-03-23'))->toSql()
        );
    }

    public function test_it_can_scope_to_within_period_with_both_start_and_end_date_time(): void
    {
        Post::factory()->create();

        $this->assertEquals(
            'select * from "views" where "viewed_at" between ? and ?',
            View::withinPeriod(Period::create('2019-02-15', '2019-06-12'))->toSql()
        );
    }

    public function test_it_can_scope_to_collection_null(): void
    {
        Post::factory()->create();

        $this->assertEquals(
            'select * from "views" where "collection" is null',
            View::collection(null)->toSql()
        );
    }

    public function test_it_can_scope_to_collection_custom(): void
    {
        Post::factory()->create();

        $this->assertEquals(
            'select * from "views" where "collection" = ?',
            View::collection('custom')->toSql()
        );
    }
}
