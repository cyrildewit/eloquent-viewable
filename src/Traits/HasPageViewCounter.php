<?php

namespace CyrildeWit\PageViewCounter\Traits;

use Carbon\Carbon;
use CyrildeWit\PageViewCounter\Classes\SessionHistory;
use Illuminate\Http\Request;

/**
 * Trait HasPageVisitsCounter for Eloquent models.
 *
 * @copyright  Copyright (c) 2017 Cyril de Wit (http://www.cyrildewit.nl)
 * @author     Cyril de Wit (info@cyrildewit.nl)
 * @license    https://opensource.org/licenses/MIT    MIT License
 */
trait HasPageViewCounter
{
    protected $dateTransformers;
    protected $sessionHistoryInstance;

    /**
     * Create a new Eloquent model instance.
     *
     * @param  array  $attributes
     * @return void
     */
    public function __construct(array $attributes = [])
    {
        // $this->dateTransformers = parent::
        $this->sessionHistoryInstance = new SessionHistory();

        return parent::__construct($attributes);
    }

    /**
     * Get the page visits associated with the given model.
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
     * Retrieve page views based upon the given requirement.
     *
     * @param  \Carbon\Carbon|null  $sinceDate
     * @param  \Carbon\Carbon|null  $uptoDate
     * @param  boolean  $unique  Should the page views be unique.
     * @param  boolean  $shouldFormatted Should the output be formatted.
     * @return integer|string  Page views as integer or formatted string.
     */
    public function retrievePageViews($sinceDate = null, $uptoDate = null, bool $unique = false, bool $shouldFormatted = false)
    {
        $query = $this->views();

        if ($sinceDate) {
            $query->where('created_at', '>=', $sinceDate);
        }

        if ($uptoDate) {
            $query->where('created_at', '=<', $sinceDate);
        }

        if ($unique) {
            $query->distinct('ip_address');
        }

        $pageViews = $query->count();

        if ($shouldFormatted) {
            $options = config('page-view-counter.output-settings.format-options');

            return number_format(
                $pageViews,
                $options['decimals'],
                $options['dec_point'],
                $options['thousands_sep']
            );
        }

        return $pageViews;
    }

    /**
     * Get the total number of page views.
     *
     * @return integer
     */
    public function getPageViews()
    {
        return $this->retrievePageViews();
    }

    /**
     * Get the total number of page views starting from the given date.
     *
     * @param  \Carbon\Carbon  $sinceDate
     * @return integer|string  Page views as integer or formatted string.
     */
    public function getPageViewsFrom(Carbon $sinceDate)
    {
        $sinceDate = $this->transformDate($sinceDate);

        return $this->retrievePageViews($sinceDate);
    }

    /**
     * Get the total number of page views between two dates.
     *
     * @param  \Carbon\Carbon  $sinceDate
     * @param  \Carbon\Carbon  $uptoDate
     * @return integer
     */
    public function getPageViewsBetween(Carbon $sinceDate, Carbon $uptoDate)
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
    public function addView()
    {
        $viewClass = config('page-view-counter.page_view_model');

        $newView = new $viewClass();
        $newView->visitable_id = $this->id;
        $newView->visitable_type = get_class($this);
        $newView->ip_address = \Request::ip();
        $this->views()->save($newView);

        return $newView;
    }

    /**
     * Add a new page view and store it into the session with an expiry date.
     *
     * @param  \Carbon\Carbon $expiryDate [description]
     * @return boolean
     */
    public function addViewThatExpiresAt(Carbon $expiryDate)
    {
        if ($this->sessionHistoryInstance->addToSession($this, $expiryDate)) {
            $this->addView();

            return true;
        }

        return fales;
    }

    /**
     * Transform the given value to a date based on defined transformers.
     *
     * @param  [type] $date
     * @return \Carbon\Carbon
     */
    protected function transformDate($date)
    {
        $transformers = [
            '24h' => Carbon::now()->subDays(1),
            '7d' => Carbon::now()->subWeeks(1),
            '14d' => Carbon::now()->subWeeks(2),
        ];

        foreach($transformers as $key => $transformer) {
            if ($key === $date) {
                return $transformer;
            }
        }

        return $date;
    }
}
