<?php

namespace Cyrildewit\PageVisitsCounter\Traits;

use Carbon\Carbon;
use Cyrildewit\PageVisitsCounter\Classes\SessionHistory;

/**
 * Trait HasPageVisitsCounter for Eloquent models.
 *
 * @copyright  Copyright (c) 2017 Cyril de Wit (http://www.cyrildewit.nl)
 * @author     Cyril de Wit (info@cyrildewit.nl)
 * @license    https://opensource.org/licenses/MIT    MIT License
 */
trait HasPageVisitsCounter
{
    /** @var array */
    protected $configSettings;

    /**
     * HasPageVisitsCounter constructor function.
     *
     * @return void
     */
    public function __construct(array $attributes = [])
    {
        $this->configSettings = config('page-visits-counter');
        return parent::__construct($attributes);
    }

    /**
     * Adding attributes for retrieving the pagevies of the model.
     *
     * @var arrayls

     */
    protected function getArrayableAppends()
    {
        $this->appends = array_unique(array_merge($this->appends, [
            'page_visits',
            'page_visits_24h',
            'page_visits_7d',
            'page_visits_14d',
            'page_visits_formatted',
            'page_visits_24h_formatted',
            'page_visits_7d_formatted',
            'page_visits_14d_formatted',
        ]));

        return parent::getArrayableAppends();
    }

    /**
     * Get the page visits associated with the given model.
     *
     * @return \Illuminate\Database\Eloquent\Relations\MorphMany
     */
    public function visits()
    {
        return $this->morphMany(
            $this->configSettings['page_visit_model'],
            'visitable'
        );
    }

    /**
     * Count all page visits together of the model.
     *
     * @return int
     */
    public function getPageVisitsAttribute()
    {
        return $this->visits()->count();
    }

    /**
     * Count all page visits together of the model and format it.
     *
     * @return int
     */
    public function getPageVisitsFormattedAttribute()
    {
        return $this->formatIntegerHumanReadable($this->getPageVisitsAttribute());
    }

    /**
     * Count all page visits from the last 24 hours.
     *
     * @return int
     */
    public function getPageVisits24hAttribute()
    {
        return $this->retrievePageVisitsCountFrom(Carbon::now()->subHours(24));
    }

    /**
     * Count all page visits from the last 24 hours and format it.
     *
     * @return int
     */
    public function getPageVisits24hFormattedAttribute()
    {
        return $this->formatIntegerHumanReadable($this->getPageVisits24hAttribute());
    }

    /**
     * Count all page visits from the last 7 weeks.
     *
     * @return int
     */
    public function getPageVisits7dAttribute()
    {
        return $this->retrievePageVisitsCountFrom(Carbon::now()->subDays(7));
    }

    /**
     * Count all page visits from the last 7 weeks and format it.
     *
     * @return int
     */
    public function getPageVisits7dFormattedAttribute()
    {
        return $this->formatIntegerHumanReadable($this->getPageVisits7dAttribute());
    }

    /**
     * Count all page visits from the last 14 days.
     *
     * @return int
     */
    public function getPageVisits14dAttribute()
    {
        return $this->retrievePageVisitsCountFrom(Carbon::now()->subDays(14));
    }

    /**
     * Count all page visits from the last 14 days and format it.
     *
     * @return int
     */
    public function getPageVisits14dFormattedAttribute()
    {
        return $this->formatIntegerHumanReadable($this->getPageVisits14dAttribute());
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
     * Retrieve the counted visits.
     *
     * @param \Carbon\Carbon $start_date
     * @param \Carbon\Carbon $end_date
     * @return int
     */
    public function retrievePageVisitsCountBetween(Carbon $from_date, Carbon $end_date)
    {
        $countResult = $this
            ->visits()
            ->where('created_at', '>=', $from_date)
            ->where('created_at', '=<', $end_date)
            ->count();

        return $this->convertNumber($countResult);
    }

    /**
     * Adds a visit to the given model and return instance of the visit.
     *
     * @return \Illuminate\Database\Eloquent\Model
     */
    public function addVisit()
    {
        $visitClass = $this->configSettings['page_visit_model'];

        $visit = new $visitClass();
        $visit->visitable_id = $this->id;
        $visit->visitable_type = get_class($this);
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
