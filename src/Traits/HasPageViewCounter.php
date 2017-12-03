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
    /** @var array */
    protected $configSettings;

    protected $viewAttributes;

    /**
     * HasPageViewCounter constructor function.
     *
     * @return void
     */
    public function __construct()
    {
        $this->configSettings = config('page-view-counter');
        $this->viewAttributes = $viewAttributes;

        runkit_method_add();

        return parent::__construct();
    }

    public function getViewAttributes()
    {
        return parent::getArrayableAppends();
    }

    // in model: $viewAttributes = [
    //    'page_visits',
    //    'page_visits_24h'
    // ];

    // :: page_visits
    // :: unique_page_visits
    //
    // page_visits
    //

    /**
     * Get the page visits associated with the given model.
     *
     * @return \Illuminate\Database\Eloquent\Relations\MorphMany
     */
    public function visits()
    {
        return $this->morphMany(
            $this->configSettings['page_view_model'],
            'visitable'
        );
    }

    /**
     * Count all page visits of a certain time together.
     *
     * @param \Carbon\Carbon $start_date
     * @return int
     */
    public function retrievePageVisitsCountFrom(Carbon $from_date)
    {
        return $this
            ->visits()
            ->where('created_at', '>=', $from_date)
            ->count();
    }

    /**
     * Count all page visits of a certain time together.
     *
     * @param \Carbon\Carbon $start_date
     * @return int
     */
    public function retrieveUniquePageVisitsCountFrom(Carbon $from_date)
    {
        return $this
            ->visits()
            ->where('created_at', '>=', $from_date)
            ->get()
            ->unique('ip_address')
            ->count();
    }

    /**
     * Retrieve the counted visits.
     *
     * @param \Carbon\Carbon $start_date
     * @param \Carbon\Carbon $end_date
     * @return int
     */
    public function retrievePageVisitsCountBetween(Carbon $from_date, Carbon $end_date)
    {
        return $this
            ->visits()
            ->where('created_at', '>=', $from_date)
            ->where('created_at', '=<', $end_date)
            ->count();
    }

    /**
     * Adds a visit to the given model and return instance of the visit.
     *
     * @return \Illuminate\Database\Eloquent\Model
     */
    public function addVisit()
    {
        $visitClass = $this->configSettings['page_view_model'];

        $visit = new $visitClass();
        $visit->visitable_id = $this->id;
        $visit->visitable_type = get_class($this);
        $visit->ip_address = \Request::ip();
        $this->visits()->save($visit);

        return $visit;
    }

    /**
     * Save new visit into the database and return the current number of visits.
     *
     * @return int
     */
    public function addVisitAndCountAll()
    {
        $this->addVisit();

        return $this->getTotalVisitsCountAttribute();
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
     * Save new visit with expiry date return the current number of visits.
     *
     * @return int
     */
    public function addVisitThatExpiresAtAndCountAll()
    {
        $this->addVisit();

        return $this->getTotalVisitsCountAttribute();
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
        $options = $this->configSettings['output-settings']['format-options'];

        return number_format(
            $number,
            $options['decimals'],
            $options['dec_point'],
            $options['thousands_sep']
        );
    }
}
