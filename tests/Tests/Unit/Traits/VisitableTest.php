<?php

declare(strict_types=1);

/*
 * This file is part of Eloquent Visitable.
 *
 * (c) Cyril de Wit <github@cyrildewit.nl>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace CyrildeWit\EloquentVisitable\Tests\Unit\Helpers;

use Carbon\Carbon;
use CyrildeWit\EloquentVisitable\Models\Visit;
use CyrildeWit\EloquentVisitable\Tests\TestCase;
use CyrildeWit\EloquentVisitable\Tests\Stubs\Models\Post;

/**
 * Class VisitableTest.
 *
 * @author Cyril de Wit <github@cyrildewit.nl>
 */
class VisitableTest extends TestCase
{
    /** @test */
    public function it_can_add_a_new_visit_to_a_model()
    {
        $post = factory(Post::class)->create();

        $post->addVisit();

        $this->assertEquals(1, $post->getVisitsCount());
    }

    /** @test */
    public function it_can_have_multiple_visits()
    {
        $post = factory(Post::class)->create();

        $times = 7;

        for ($i = 0; $i < $times; $i++) {
            $post->addVisit();
        }

        $this->assertEquals($times, $post->getVisitsCount());
    }

    /** @test */
    public function it_can_return_the_total_number_of_visits_since_the_given_date()
    {
        $post = factory(Post::class)->create();

        $this->createNewVisit($post, Carbon::parse('2018-01-01 12:00:00'));
        $this->createNewVisit($post, Carbon::parse('2018-01-02 12:00:00'));
        $this->createNewVisit($post, Carbon::parse('2018-01-03 12:00:00'));

        $this->createNewVisit($post, Carbon::parse('2018-02-01 12:00:00'));
        $this->createNewVisit($post, Carbon::parse('2018-02-02 12:00:00'));
        $this->createNewVisit($post, Carbon::parse('2018-02-03 12:00:00'));
        $this->createNewVisit($post, Carbon::parse('2018-02-04 12:00:00'));

        $this->createNewVisit($post, Carbon::parse('2018-03-01 12:00:00'));
        $this->createNewVisit($post, Carbon::parse('2018-03-02 12:00:00'));

        $this->assertEquals(9, $post->getVisitsCount());

        $this->assertEquals(9, $post->getVisitsCountSince(Carbon::parse('2018-01-01 12:00:00')));
        $this->assertEquals(7, $post->getVisitsCountSince(Carbon::parse('2018-01-03 12:00:00')));
        $this->assertEquals(4, $post->getVisitsCountSince(Carbon::parse('2018-02-03 12:00:00')));
        $this->assertEquals(1, $post->getVisitsCountSince(Carbon::parse('2018-03-02 12:00:00')));
    }

    /** @test */
    public function it_can_return_the_total_number_of_visits_upto_the_given_date()
    {
        $post = factory(Post::class)->create();

        $this->createNewVisit($post, Carbon::parse('2018-01-01 12:00:00'));
        $this->createNewVisit($post, Carbon::parse('2018-01-02 12:00:00'));
        $this->createNewVisit($post, Carbon::parse('2018-01-03 12:00:00'));

        $this->createNewVisit($post, Carbon::parse('2018-02-01 12:00:00'));
        $this->createNewVisit($post, Carbon::parse('2018-02-02 12:00:00'));
        $this->createNewVisit($post, Carbon::parse('2018-02-03 12:00:00'));
        $this->createNewVisit($post, Carbon::parse('2018-02-04 12:00:00'));

        $this->createNewVisit($post, Carbon::parse('2018-03-01 12:00:00'));
        $this->createNewVisit($post, Carbon::parse('2018-03-02 12:00:00'));

        $this->assertEquals(9, $post->getVisitsCount());

        $this->assertEquals(0, $post->getVisitsCountUpto(Carbon::parse('2018-01-01 06:00:00')));
        $this->assertEquals(3, $post->getVisitsCountUpto(Carbon::parse('2018-02-01 06:00:00')));
        $this->assertEquals(7, $post->getVisitsCountUpto(Carbon::parse('2018-03-01 06:00:00')));
        $this->assertEquals(9, $post->getVisitsCountUpto(Carbon::parse('2018-04-01 06:00:00')));
    }

    /** @test */
    public function it_can_return_the_total_number_of_visits_between_the_given_dates()
    {
        $post = factory(Post::class)->create();

        $this->createNewVisit($post, Carbon::parse('2018-01-01 12:00:00'));
        $this->createNewVisit($post, Carbon::parse('2018-01-02 12:00:00'));
        $this->createNewVisit($post, Carbon::parse('2018-01-03 12:00:00'));

        $this->createNewVisit($post, Carbon::parse('2018-02-01 12:00:00'));
        $this->createNewVisit($post, Carbon::parse('2018-02-02 12:00:00'));
        $this->createNewVisit($post, Carbon::parse('2018-02-03 12:00:00'));
        $this->createNewVisit($post, Carbon::parse('2018-02-04 12:00:00'));

        $this->createNewVisit($post, Carbon::parse('2018-03-01 12:00:00'));
        $this->createNewVisit($post, Carbon::parse('2018-03-02 12:00:00'));

        $this->assertEquals(9, $post->getVisitsCount());

        $this->assertEquals(3, $post->getVisitsCountBetween(
            Carbon::parse('2018-01-01 06:00:00'),
            Carbon::parse('2018-01-03 12:00:00')
        ));
        $this->assertEquals(3, $post->getVisitsCountBetween(
            Carbon::parse('2018-02-01 12:00:00'),
            Carbon::parse('2018-02-03 12:00:00')
        ));
        $this->assertEquals(8, $post->getVisitsCountBetween(
            Carbon::parse('2018-01-02 12:00:00'),
            Carbon::parse('2018-03-02 12:00:00')
        ));
    }

    /**
     * Helper function to create a visit.
     *
     * @return \CyrildeWit\EloquentVisitable\Models\Visit
     */
    public function createNewVisit($model, $createdAt)
    {
        return Visit::create([
            'visitable_id' => $model->getKey(),
            'visitable_type' => get_class($model),
            'ip_address' => '130.57.143.127',
            'created_at' => $createdAt,
        ]);
    }
}
