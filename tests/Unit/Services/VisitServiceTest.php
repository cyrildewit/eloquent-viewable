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

namespace CyrildeWit\EloquentVisitable\Tests\Unit\Cache;


use Carbon\Carbon;
use CyrildeWit\EloquentVisitable\Models\Visit;
use CyrildeWit\EloquentVisitable\Tests\TestCase;
use CyrildeWit\EloquentVisitable\Services\VisitService;
use CyrildeWit\EloquentVisitable\Tests\Stubs\Models\Post;
use CyrildeWit\EloquentVisitable\Cache\VisitCounterCacheRepository;

/**
 * Class VisitServiceTest.
 *
 * @author Cyril de Wit <github@cyrildewit.nl>
 */
class VisitServiceTest extends TestCase
{
    /** @test */
    public function it_can_instantiate_service()
    {
        $service = $this->app->make(VisitService::class);

        $this->assertInstanceOf(VisitService::class, $service);
    }

    /** @test */
    public function getVisitsCount_it_can_cache_the_counts()
    {
        $service = $this->app->make(VisitService::class);
        $cacheRepository = $this->app->make(VisitCounterCacheRepository::class);
        $post = factory(Post::class)->create();

        $post->addVisit();
        $post->addVisit();
        $post->addVisit();

        $service->getVisitsCount($post);

        $cachedVisitsCount = $cacheRepository->get($post, 'normal', '|');

        $this->assertEquals(3, $cachedVisitsCount);
    }

    /** @test */
    public function it_can_count_visits()
    {
        $service = $this->app->make(VisitService::class);
        $post = factory(Post::class)->create();

        $this->createNewVisit($post, Carbon::parse('2018-01-01 12:00:00'));
        $this->createNewVisit($post, Carbon::parse('2018-01-02 12:00:00'));

        $this->createNewVisit($post, Carbon::parse('2018-02-01 12:00:00'));
        $this->createNewVisit($post, Carbon::parse('2018-02-02 12:00:00'));
        $this->createNewVisit($post, Carbon::parse('2018-02-03 12:00:00'));

        $this->createNewVisit($post, Carbon::parse('2018-03-01 12:00:00'));
        $this->createNewVisit($post, Carbon::parse('2018-03-02 12:00:00'));
        $this->createNewVisit($post, Carbon::parse('2018-03-03 12:00:00'));

        $this->assertEquals(8, $service->countVisits($post));
    }

    /** @test */
    public function it_can_count_visits_with_since_date()
    {
        $service = $this->app->make(VisitService::class);
        $post = factory(Post::class)->create();

        $this->createNewVisit($post, Carbon::parse('2018-01-01 12:00:00'));
        $this->createNewVisit($post, Carbon::parse('2018-01-02 12:00:00'));

        $this->createNewVisit($post, Carbon::parse('2018-02-01 12:00:00'));
        $this->createNewVisit($post, Carbon::parse('2018-02-02 12:00:00'));
        $this->createNewVisit($post, Carbon::parse('2018-02-03 12:00:00'));

        $this->createNewVisit($post, Carbon::parse('2018-03-01 12:00:00'));
        $this->createNewVisit($post, Carbon::parse('2018-03-02 12:00:00'));
        $this->createNewVisit($post, Carbon::parse('2018-03-03 12:00:00'));

        $this->assertEquals(6, $service->countVisits($post, Carbon::parse('2018-02-01 12:00:00')));
        $this->assertEquals(3, $service->countVisits($post, Carbon::parse('2018-03-01 12:00:00')));
    }

    /** @test */
    public function it_can_count_visits_with_upto_date()
    {
        $service = $this->app->make(VisitService::class);
        $post = factory(Post::class)->create();

        $this->createNewVisit($post, Carbon::parse('2018-01-01 12:00:00'));
        $this->createNewVisit($post, Carbon::parse('2018-01-02 12:00:00'));

        $this->createNewVisit($post, Carbon::parse('2018-02-01 12:00:00'));
        $this->createNewVisit($post, Carbon::parse('2018-02-02 12:00:00'));
        $this->createNewVisit($post, Carbon::parse('2018-02-03 12:00:00'));

        $this->createNewVisit($post, Carbon::parse('2018-03-01 12:00:00'));
        $this->createNewVisit($post, Carbon::parse('2018-03-02 12:00:00'));
        $this->createNewVisit($post, Carbon::parse('2018-03-03 12:00:00'));

        $this->assertEquals(3, $service->countVisits($post, null, Carbon::parse('2018-02-01 12:00:00')));
        $this->assertEquals(6, $service->countVisits($post, null, Carbon::parse('2018-03-01 12:00:00')));
    }

    /** @test */
    public function it_can_count_visits_with_since_and_upto_date()
    {
        $service = $this->app->make(VisitService::class);
        $post = factory(Post::class)->create();

        $this->createNewVisit($post, Carbon::parse('2018-01-01 12:00:00'));
        $this->createNewVisit($post, Carbon::parse('2018-01-02 12:00:00'));

        $this->createNewVisit($post, Carbon::parse('2018-02-01 12:00:00'));
        $this->createNewVisit($post, Carbon::parse('2018-02-02 12:00:00'));
        $this->createNewVisit($post, Carbon::parse('2018-02-03 12:00:00'));

        $this->createNewVisit($post, Carbon::parse('2018-03-01 12:00:00'));
        $this->createNewVisit($post, Carbon::parse('2018-03-02 12:00:00'));
        $this->createNewVisit($post, Carbon::parse('2018-03-03 12:00:00'));

        $this->assertEquals(6, $service->countVisits($post, Carbon::parse('2018-01-01 12:00:00'), Carbon::parse('2018-03-01 12:00:00')));
        $this->assertEquals(3, $service->countVisits($post, Carbon::parse('2018-03-01 12:00:00'), Carbon::parse('2018-03-03 12:00:00')));
    }

    /** @test */
    public function it_can_count_unique_visits()
    {
        $service = $this->app->make(VisitService::class);
        $post = factory(Post::class)->create();

        $this->createNewVisit($post, Carbon::parse('2018-01-01 12:00:00'), '80.57.143.127');
        $this->createNewVisit($post, Carbon::parse('2018-01-02 12:00:00'), '130.57.143.127');

        $this->createNewVisit($post, Carbon::parse('2018-02-01 12:00:00'), '80.57.143.127');
        $this->createNewVisit($post, Carbon::parse('2018-02-02 12:00:00'), '130.57.143.127');
        $this->createNewVisit($post, Carbon::parse('2018-02-03 12:00:00'), '130.57.143.127');

        $this->createNewVisit($post, Carbon::parse('2018-03-01 12:00:00'), '130.57.143.127');
        $this->createNewVisit($post, Carbon::parse('2018-03-02 12:00:00'), '80.57.143.127');
        $this->createNewVisit($post, Carbon::parse('2018-03-03 12:00:00'), '70.57.143.127');

        $this->assertEquals(3, $service->countVisits($post, null, null, true));
    }

    /** @test */
    public function it_can_count_unique_visits_with_since_date()
    {
        $service = $this->app->make(VisitService::class);
        $post = factory(Post::class)->create();

        $this->createNewVisit($post, Carbon::parse('2018-01-01 12:00:00'), '80.57.143.127');
        $this->createNewVisit($post, Carbon::parse('2018-01-02 12:00:00'), '130.57.143.127');

        $this->createNewVisit($post, Carbon::parse('2018-02-01 12:00:00'), '80.57.143.127');
        $this->createNewVisit($post, Carbon::parse('2018-02-02 12:00:00'), '130.57.143.127');
        $this->createNewVisit($post, Carbon::parse('2018-02-03 12:00:00'), '130.57.143.127');

        $this->createNewVisit($post, Carbon::parse('2018-03-01 12:00:00'), '130.57.143.127');
        $this->createNewVisit($post, Carbon::parse('2018-03-02 12:00:00'), '80.57.143.127');
        $this->createNewVisit($post, Carbon::parse('2018-03-03 12:00:00'), '70.57.143.127');

        $this->assertEquals(3, $service->countVisits($post, Carbon::parse('2018-02-01 12:00:00'), null, true));
        $this->assertEquals(2, $service->countVisits($post, Carbon::parse('2018-03-02 12:00:00'), null, true));
    }

    /** @test */
    public function it_can_count_unique_visits_with_upto_date()
    {
        $service = $this->app->make(VisitService::class);
        $post = factory(Post::class)->create();

        $this->createNewVisit($post, Carbon::parse('2018-01-01 12:00:00'), '80.57.143.127');
        $this->createNewVisit($post, Carbon::parse('2018-01-02 12:00:00'), '130.57.143.127');

        $this->createNewVisit($post, Carbon::parse('2018-02-01 12:00:00'), '80.57.143.127');
        $this->createNewVisit($post, Carbon::parse('2018-02-02 12:00:00'), '130.57.143.127');
        $this->createNewVisit($post, Carbon::parse('2018-02-03 12:00:00'), '130.57.143.127');

        $this->createNewVisit($post, Carbon::parse('2018-03-01 12:00:00'), '130.57.143.127');
        $this->createNewVisit($post, Carbon::parse('2018-03-02 12:00:00'), '80.57.143.127');
        $this->createNewVisit($post, Carbon::parse('2018-03-03 12:00:00'), '70.57.143.127');

        $this->assertEquals(2, $service->countVisits($post, null, Carbon::parse('2018-02-01 12:00:00'), true));
        $this->assertEquals(2, $service->countVisits($post, null, Carbon::parse('2018-03-01 12:00:00'), true));
        $this->assertEquals(3, $service->countVisits($post, null, Carbon::parse('2018-03-03 12:00:00'), true));
    }

    /** @test */
    public function it_can_count_unique_visits_with_since_and_upto_date()
    {
        $service = $this->app->make(VisitService::class);
        $post = factory(Post::class)->create();

        $this->createNewVisit($post, Carbon::parse('2018-01-01 12:00:00'), '80.57.143.127');
        $this->createNewVisit($post, Carbon::parse('2018-01-02 12:00:00'), '130.57.143.127');

        $this->createNewVisit($post, Carbon::parse('2018-02-01 12:00:00'), '80.57.143.127');
        $this->createNewVisit($post, Carbon::parse('2018-02-02 12:00:00'), '130.57.143.127');
        $this->createNewVisit($post, Carbon::parse('2018-02-03 12:00:00'), '130.57.143.127');

        $this->createNewVisit($post, Carbon::parse('2018-03-01 12:00:00'), '130.57.143.127');
        $this->createNewVisit($post, Carbon::parse('2018-03-02 12:00:00'), '80.57.143.127');
        $this->createNewVisit($post, Carbon::parse('2018-03-03 12:00:00'), '70.57.143.127');

        $this->assertEquals(2, $service->countVisits($post, Carbon::parse('2018-01-01 12:00:00'), Carbon::parse('2018-03-01 12:00:00'), true));
        $this->assertEquals(3, $service->countVisits($post, Carbon::parse('2018-03-01 12:00:00'), Carbon::parse('2018-03-03 12:00:00'), true));
    }

    /**
     * Helper function to create a visit.
     *
     * @return \CyrildeWit\EloquentVisitable\Models\Visit
     */
    public function createNewVisit($model, $createdAt, $ipAddress = null)
    {
        return Visit::create([
            'visitable_id' => $model->getKey(),
            'visitable_type' => get_class($model),
            'ip_address' => $ipAddress ?? '130.57.143.127',
            'created_at' => $createdAt,
        ]);
    }
}
