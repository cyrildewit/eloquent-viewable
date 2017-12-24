<?php

namespace CyrildeWit\PageViewCounter\Tests\TestCases;

use Carbon\Carbon;
use CyrildeWit\PageViewCounter\Tests\TestCase;
use CyrildeWit\PageViewCounter\Helpers\SessionHistory;

class ViewViewableTest extends TestCase
{
    /**
     * Setup the test environment.
     *
     * @return void
     */
    public function setUp()
    {
        parent::setUp();
    }

    /** @test */
    public function it_can_store_page_views_in_the_database()
    {
        // Store a new page view (1)
        $this->testTaskModel->addPageView();
        $hasFirstView = $this->testTaskModel->getPageViews() === 1 ? true : false;
        $this->assertTrue($hasFirstView);

        // Store a new page view (2)
        $this->testTaskModel->addPageView();
        $hasSecondView = $this->testTaskModel->getPageViews() === 2 ? true : false;
        $this->assertTrue($hasSecondView);

        // Store a new page view (3)
        $this->testTaskModel->addPageView();
        $hasThirdView = $this->testTaskModel->getPageViews() === 3 ? true : false;
        $this->assertTrue($hasThirdView);
    }

    /** @test */
    public function it_can_store_page_views_with_and_expiry_date_in_the_database()
    {
        // Make a unique key to retrieve the page views history from the session
        $uniqueKey = snake_case(class_basename($this->testTaskModel));
        $viewable_id = $this->testTaskModel->id;

        // Store a new page view with expiry date
        $this->testTaskModel->addPageViewThatExpiresAt(Carbon::now()->addSeconds(40));

        // Check if the page view has been stored in the database
        $hasPageView = $this->testTaskModel->getPageViews() === 1 ? true : false;
        $this->assertTrue($hasPageView);

        // Check if the page view has been stored in the sesssion
        $hasPageViewInSession = (new SessionHistory())->isItemVisited($uniqueKey, $viewable_id);
        $this->assertTrue($hasPageViewInSession);
    }
}
