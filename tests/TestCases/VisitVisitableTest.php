<?php

namespace Cyrildewit\PageVisitsCounter\Test\TestCases;

use Cyrildewit\PageVisitsCounter\Test\TestCase;
use Cyrildewit\PageVisitsCounter\Classes\SessionHistory;
use Carbon\Carbon;
use Illuminate\Support\Facades\Session;

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

    /** @test */
    public function it_can_store_new_visits_with_expiry_dates_into_the_database()
    {
        $uniqueKey = (new SessionHistory())->fromCamelCaseToDashes(class_basename($this->testTaskModel));
        $visitable_id = $this->testTaskModel->id;

        // Store new visit
        $this->testTaskModel->addVisitThatExpiresAt(Carbon::now()->addSeconds(40));

        $hasNewVisit = ($this->testTaskModel->total_visits_count->number === 1 ? true : false);
        $this->assertTrue($hasNewVisit);

        $hasNewVisitInSession = (new SessionHistory())->isItemVisited($uniqueKey, $visitable_id);
        $this->assertTrue($hasNewVisitInSession);
    }
}
