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
trait HasPageVisits
{
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
}
