<?php

namespace Cyrildewit\PageViewCounter\Traits;

use Carbon\Carbon;
use Cyrildewit\PageViewCounter\Classes\SessionHistory;
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

    /**
     * Create a new Eloquent model instance.
     *
     * @param  array  $attributes
     * @return void
     */
    public function __construct(array $attributes = [])
    {
        // $this->dateTransformers = parent::

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

    // addView()
    // addViewThatExpiresAt()


    /**
     * Get the total number of page views.
     *
     * @return int
     */
    public function getPageViews()
    {
        return $this->views()->count();
    }

    /**
     * Get the total number of page views starting from the given date.
     *
     * @param  \Carbon\Carbon  $sinceDate
     * @return int
     */
    public function getPageViewsFrom(Carbon $sinceDate)
    {
        $sinceDate = $this->transformDate($sinceDate);

        return $this
            ->views()
            ->where('created_at', '>=', $sinceDate)
            ->count();
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

        return $this
            ->views()
            ->where('created_at', '>=', $sinceDate)
            ->where('created_at', '=<', $uptoDate)
            ->count();
    }

    /**
     * Get the total number of page views.
     *
     * @return int
     */
    public function getUniquePageViews()
    {
        return $this->views()->distinct('ip_address')->count();
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

        return $this
            ->views()
            ->distinct('ip_address')
            ->where('created_at', '>=', $sinceDate)
            ->count();
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

        return $this
            ->views()
            ->distinct('ip_address')
            ->where('created_at', '>=', $sinceDate)
            ->where('created_at', '=<', $uptoDate)
            ->count();
    }

    /**
     * Transform the given value to a date based on defined transformers.
     *
     * @param  [type] $date
     * @return \Carbon\Carbon
     */
    public function transformDate($date)
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


    public function addViewThatExpiresAt()
    {
        if ((new SessionHistory())->addToSession($this, $expires_at)) {
            $this->addVisit();
        }
    }



    /**
     * Adds a visit tot the given model and store it into the session with an expiry date.
     *
     * @param \Carbon\Carbon $expires_at
     * @return ture|false
     */
    public function addVisitThatExpiresAt(Carbon $expires_at)
    {
        if ((new SessionHistory())->addToSession($this, $expires_at)) {
            $this->addVisit();
        }
    }

    /**
     * Format an integer to a human readable version.
     *
     * @param int $number
     * @param array $options
     * @return string
     */
    protected function formatIntegerHumanReadable($number)
    {
        $options = config('page-view-counter.output-settings.format-options');

        return number_format(
            $number,
            $options['decimals'],
            $options['dec_point'],
            $options['thousands_sep']
        );
    }
}
