<?php

namespace CyrildeWit\PageViewCounter\Traits;

// use DB;
use Request;
use Carbon\Carbon;
use CyrildeWit\PageViewCounter\Helpers\SessionHistory;

/**
 * Trait HasPageViewCounter for Eloquent models.
 *
 * @copyright  Copyright (c) 2017 Cyril de Wit (http://www.cyrildewit.nl)
 * @author     Cyril de Wit (info@cyrildewit.nl)
 * @license    https://opensource.org/licenses/MIT    MIT License
 */
trait HasPageViewCounter
{
    /**
     * Instance of SessionHistory.
     *
     * @var \CyrildeWit\PageViewCounter\Helpers\SessionHistory
     */
    protected $sessionHistoryInstance;

    /**
     * Create a new Eloquent model instance.
     *
     * @param  array  $attributes
     * @return void
     */
    public function __construct(array $attributes = [])
    {
        $this->sessionHistoryInstance = new SessionHistory();

        parent::__construct($attributes);
    }

    /**
     * Get the page views associated with the given model.
     *
     * @return \Illuminate\Database\Eloquent\Relations\MorphMany
     */
    public function views()
    {
        return $this->morphMany(
            config('page-view-counter.page_view_model'),
            'visitable'
        );
    }

    /**
     * Retrieve page views based upon the given options.
     *
     * @param  \Carbon\Carbon|null  $sinceDate
     * @param  \Carbon\Carbon|null  $uptoDate
     * @param  boolean  $unique  Should the page views be unique.
     * @return int|string
     */
    public function retrievePageViews($sinceDate = null, $uptoDate = null, bool $unique = false)
    {
        // Create new Query Builder instance of views relationship
        $query = $this->views();

        // Apply the following if the since date is given
        if ($sinceDate) {
            $query->where('created_at', '>=', $sinceDate);
        }

        // Apply the following if the upto date is given
        if ($uptoDate) {
            $query->where('created_at', '=<', $sinceDate);
        }

        // Apply the following if page views should be unique
        if ($unique) {
            $query->select('ip_address')->groupBy('ip_address');
        }

        // If the unique option is false then just use the SQL count method,
        // otherwise get the results and count them
        $countedPageViews = ! $unique ? $query->count() : $query->get()->count();

        return $countedPageViews;
    }

    /**
     * Get the total number of page views.
     *
     * @return int
     */
    public function getPageViews()
    {
        return $this->retrievePageViews();
    }

    /**
     * Get the total number of page views starting from the given date.
     *
     * @param  \Carbon\Carbon|string  $sinceDate
     * @return int
     */
    public function getPageViewsFrom($sinceDate)
    {
        $sinceDate = $this->transformDate($sinceDate);

        return $this->retrievePageViews($sinceDate);
    }

    /**
     * Get the total number of page views between two dates.
     *
     * @param  \Carbon\Carbon|string  $sinceDate
     * @param  \Carbon\Carbon|string  $uptoDate
     * @return int
     */
    public function getPageViewsBetween($sinceDate, $uptoDate)
    {
        $sinceDate = $this->transformDate($sinceDate);
        $uptoDate = $this->transformDate($uptoDate);

        return $this->retrievePageViews($sinceDate, $uptoDate);
    }

    /**
     * Get the total number of page views.
     *
     * @return int
     */
    public function getUniquePageViews()
    {
        return $this->retrievePageViews(null, null, true);
    }

    /**
     * Get the total number of page views starting from the given date.
     *
     * @param  \Carbon\Carbon  $sinceDate
     * @return int
     */
    public function getUniquePageViewsFrom(Carbon $sinceDate)
    {
        $sinceDate = $this->transformDate($sinceDate);

        return $this->retrievePageViews($sinceDate, null, true);
    }

    /**
     * Get the total number of page views between two dates.
     *
     * @param  \Carbon\Carbon  $sinceDate
     * @param  \Carbon\Carbon  $uptoDate
     * @return int
     */
    public function getUniquePageViewsBetween(Carbon $sinceDate, Carbon $uptoDate)
    {
        $sinceDate = $this->transformDate($sinceDate);
        $uptoDate = $this->transformDate($uptoDate);

        return $this->retrievePageViews($sinceDate, $uptoDate, true);
    }

    /**
     * Add a new page view and return an instance of the page view.
     */
    public function addPageView()
    {
        $viewClass = config('page-view-counter.page_view_model');

        $newView = new $viewClass();
        $newView->visitable_id = $this->id;
        $newView->visitable_type = get_class($this);
        $newView->ip_address = Request::ip();
        $this->views()->save($newView);

        return $newView;
    }

    /**
     * Add a new page view and store it into the session with an expiry date.
     *
     * @param  \Carbon\Carbon $expiryDate [description]
     * @return boolean
     */
    public function addPageViewThatExpiresAt(Carbon $expiryDate)
    {
        if ($this->sessionHistoryInstance->addToSession($this, $expiryDate)) {
            $this->addPageView();

            return true;
        }

        return false;
    }

    /**
     * Transform the given value to a date based on defined transformers.
     *
     * @param  [type] $date
     * @return \Carbon\Carbon
     */
    protected function transformDate($date)
    {
        $transformers = config('page-view-counter.date-transformers');

        foreach($transformers as $key => $transformer) {
            if ($key === $date) {
                return $transformer;
            }
        }

        return $date;
    }
}