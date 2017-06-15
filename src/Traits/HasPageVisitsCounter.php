<?php

namespace Cyrildewit\PageVisitsCounter\Traits;

use Carbon\Carbon;
use Cyrildewit\PageVisitsCounter\Classes\SessionHistory;

/**
 * Trait for Laravel Models.
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
    public function __construct()
    {
        $this->configSettings = config('page-visits-counter.php');

        return parent::__construct();
    }

    /**
     * Adding attributes for retrieving the pagevies of the model.
     *
     * @var array
     */
    protected function getArrayableAppends()
    {
        $this->appends = array_unique(array_merge($this->appends, [
            'total_visits_count',
            'last_24h_visits_count',
            'last_7d_visits_count',
            'last_14d_visits_count',
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
            $this->configSettings['models']['page-visit'],
            'visitable'
        );
    }

    /**
     * Count all page visits together of the model.
     *
     * @return int
     */
    public function getTotalVisitsCountAttribute()
    {
        $totalNumber = $this->visits()->count();

        return $this->convertNumber($totalNumber);
    }

    /**
     * Count all page visits together of the model from the past 24 hours.
     *
     * @return int
     */
    public function getLast24hVisitsCountAttribute()
    {
        return $this->retrievePageVisitsCountFrom(Carbon::now()->subHours(24));
    }

    /**
     * Count all page visits together of the model from the past 7 days.
     *
     * @return int
     */
    public function getLast7dVisitsCountAttribute()
    {
        return $this->retrievePageVisitsCountFrom(Carbon::now()->subDays(7));
    }

    /**
     * Count all page visits together of the model from the past 14 days.
     *
     * @return int
     */
    public function getLast14dVisitsCountAttribute()
    {
        return $this->retrievePageVisitsCountFrom(Carbon::now()->subDays(14));
    }

    /**
     * Count all page visits of a certain time together.
     *
     * @param \Carbon\Carbon $start_date
     * @return int
     */
    public function retrievePageVisitsCountFrom(Carbon $from_date)
    {
        $countResult = $this
            ->visits()
            ->where('created_at', '>=', $from_date)
            ->count();

        return $this->convertNumber($countResult);
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
        $visitClass = $this->configSettings['models']['page-visit'];

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
     * Convert the visits count based upon the config settings.
     *
     * @param int $number
     * @return \stdClass
     */
    protected function convertNumber(int $number)
    {
        $output = new \stdClass();
        $output->number = $number;

        if ($this->configSettings['output-settings']['formatted-output-enabled']) {
            $options = $this->configSettings['output-settings']['format-options'];

            $output->number = $number;
            $output->formatted = $this->formatIntegerHumanReadable($number, $options);
        }

        return $output;
    }

    /**
     * Format an integer to a human readable version.
     *
     * @param int $number
     * @param array $options
     * @return string
     */
    protected function formatIntegerHumanReadable(int $number, array $options = [])
    {
        return number_format(
            $number,
            $options['decimals'],
            $options['dec_point'],
            $options['thousands_sep']
        );
    }
}
