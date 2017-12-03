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
trait HasUniquePageVisits
{
    /**
     * Adding attributes for retrieving the pagevies of the model.
     *
     * @var arrayls

     */
    protected function getArrayableAppends()
    {
        $this->appends = array_unique(array_merge($this->appends, [
            'unique_page_visits',
            // 'unique_page_visits_24h',
            // 'unique_page_visits_7d',
            // 'unique_page_visits_14d',
            // 'unique_page_visits_formatted',
            // 'unique_page_visits_24h_formatted',
            // 'unique_page_visits_7d_formatted',
            // 'unique_page_visits_14d_formatted',
        ]));

        return parent::getArrayableAppends();
    }

    /**
     * Count all page visits from the last 14 days.
     *
     * @return int
     */
    public function getUniquePageVisitsAttribute()
    {
        return $this->retrieveUniquePageVisitsCountFrom(Carbon::now()->subDays(14));
    }
}
