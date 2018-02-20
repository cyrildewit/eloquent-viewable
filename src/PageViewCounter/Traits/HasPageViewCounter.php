<?php

namespace CyrildeWit\PageViewCounter\Traits;

use Request;
use CyrildeWit\PageViewCounter\Helpers\SessionHistory;
use CyrildeWit\PageViewCounter\Helpers\DateTransformer;

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
     * @param  bool  $unique  Should the page views be unique.
     * @return int|string
     */
    public function retrievePageViews($sinceDate = null, $uptoDate = null, bool $unique = false)
    {
        // Create new Query Builder instance of the views relationship
        $query = $this->views();

        // Apply the following date filters
        if ($sinceDate && ! $uptoDate) {
            $query->where('created_at', '>=', $sinceDate);
        } elseif (! $sinceDate && $uptoDate) {
            $query->where('created_at', '=<', $uptoDate);
        } elseif ($sinceDate && $uptoDate) {
            $query->whereBetween('created_at', [$sinceDate, $uptoDate]);
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
     * Get the total number of page views after the given date.
     *
     * @param  \Carbon\Carbon|string  $sinceDate
     * @return int
     */
    public function getPageViewsFrom($sinceDate)
    {
        $sinceDate = DateTransformer::transform($sinceDate);

        return $this->retrievePageViews($sinceDate);
    }

    /**
     * Get the total number of page views before the given date.
     *
     * @param  \Carbon\Carbon|string  $uptoDate
     * @return int
     */
    public function getPageViewsBefore($uptoDate)
    {
        $uptoDate = DateTransformer::transform($uptoDate);

        return $this->retrievePageViews(null, $uptoDate);
    }

    /**
     * Get the total number of page views between the given two dates.
     *
     * @param  \Carbon\Carbon|string  $sinceDate
     * @param  \Carbon\Carbon|string  $uptoDate
     * @return int
     */
    public function getPageViewsBetween($sinceDate, $uptoDate)
    {
        $sinceDate = DateTransformer::transform($sinceDate);
        $uptoDate = DateTransformer::transform($uptoDate);

        return $this->retrievePageViews($sinceDate, $uptoDate);
    }

    /**
     * Get the total number of unique page views.
     *
     * @return int
     */
    public function getUniquePageViews()
    {
        return $this->retrievePageViews(null, null, true);
    }

    /**
     * Get the total number of unique page views after the given date.
     *
     * @param  \Carbon\Carbon|string  $sinceDate
     * @return int
     */
    public function getUniquePageViewsFrom($sinceDate)
    {
        $sinceDate = DateTransformer::transform($sinceDate);

        return $this->retrievePageViews($sinceDate, null, true);
    }

    /**
     * Get the total number of unique page views before the given date.
     *
     * @param  \Carbon\Carbon|string  $uptoDate
     * @return int
     */
    public function getUniquePageViewsBefore($uptoDate)
    {
        $uptoDate = DateTransformer::transform($uptoDate);

        return $this->retrievePageViews(null, $uptoDate, true);
    }

    /**
     * Get the total number of unique page views between the given two dates.
     *
     * @param  \Carbon\Carbon|string  $sinceDate
     * @param  \Carbon\Carbon|string  $uptoDate
     * @return int
     */
    public function getUniquePageViewsBetween($sinceDate, $uptoDate)
    {
        $sinceDate = DateTransformer::transform($sinceDate);
        $uptoDate = DateTransformer::transform($uptoDate);

        return $this->retrievePageViews($sinceDate, $uptoDate, true);
    }

    /**
     * Store a new page view and return an instance of it.
     *
     * @return \CyrildeWit\PageViewCounter\Contracts\PageView
     */
    public function addPageView()
    {
        $viewClass = config('page-view-counter.page_view_model');

        $newView = new $viewClass();
        $newView->visitable_id = $this->getKey();
        $newView->visitable_type = get_class($this);
        $newView->ip_address = Request::ip();
        $this->views()->save($newView);

        return $newView;
    }

    /**
     * Store a new page view and store it into the session with an expiry date.
     *
     * @param  \Carbon\Carbon|string  $expiryDate
     * @return bool
     */
    public function addPageViewThatExpiresAt($expiryDate)
    {
        $expiryDate = DateTransformer::transform($expiryDate);

        if ((new SessionHistory)->addToSession($this, $expiryDate)) {
            $this->addPageView();

            return true;
        }

        return false;
    }
}
