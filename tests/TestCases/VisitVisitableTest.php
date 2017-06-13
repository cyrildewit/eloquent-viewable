<?php

namespace Cyrildewit\PageVisitsCounter\Test\TestCases;

use Cyrildewit\PageVisitsCounter\Test\TestCase;

class VisitVisitableTest extends TestCase
{
    public function setUp()
    {
        parent::setUp();
    }

    /** @test */
    public function it_can_store_new_visits_into_the_database()
    {
        // Store new visit
        $this->testTaskModel->addVisit();
        $hasFirstVisit = ($this->testTaskModel->total_visits_count->number === 1 ? true : false);

        // Store new visit
        $this->testTaskModel->addVisit();
        $hasSecondVisit = ($this->testTaskModel->total_visits_count->number === 2 ? true : false);

        // Check first and second visits
        $this->assertTrue($hasFirstVisit);
        $this->assertTrue($hasSecondVisit);
    }
}
