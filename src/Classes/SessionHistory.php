<?php

namespace Cyrildewit\PageVisitsCounter\Classes;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Session;

/**
 * Class SessionHistory.
 *
 * @copyright  Copyright (c) 2017 Cyril de Wit (http://www.cyrildewit.nl)
 * @author     Cyril de Wit (info@cyrildewit.nl)
 * @license    https://opensource.org/licenses/MIT    MIT License
 */
class SessionHistory
{
    /**
     * @var string Session key where to store and retrieve the history from.
     */
    protected $primarySessionKey;

    /**
     * Construct SessionHistory.
     *
     * @return SessionHistory
     */
    public function __construct()
    {
        $this->primarySessionKey = config('page-visits-counter.sessions.primary-session-key', 'page-visits-counter.history');
    }

    /**
     * Handles the session insertings.
     *
     * @param \Illuminate\Database\Eloquent\Model $model
     * @param \Carbon\Carbon $expires_at
     * @return ture|false
     */
    public function addToSession(Model $model, Carbon $expires_at)
    {
        // Make unique key from the inserted model
        $uniqueKey = $this->fromCamelCaseToDashes(class_basename($model));

        $this->removeExpiredVisitsFromSession($uniqueKey);

        // Check if the item is visited, if not add to session
        if (! $this->isItemVisited($uniqueKey, $model->id)) {
            Session::push($this->primarySessionKey.'.'.$uniqueKey, [
                'visitable_id' => $model->id,
                'expires_at' => $expires_at,
            ]);

            return true;
        }

        return false;
    }

    /**
     * Check if the given visited item is stored in the current session.
     *
     * @param string $uniqueKey
     * @param int $visitable_id
     * @return true|false
     */
    public function isItemVisited($uniqueKey, $visitable_id)
    {
        $sessionItems = Session::get($this->primarySessionKey.'.'.$uniqueKey, []);

        foreach ($sessionItems as $sessionItem) {
            if ($sessionItem['visitable_id'] === $visitable_id) {
                return true;
            }
        }

        return false;
    }

    /**
     * Removes expired visits from the current session.
     *
     * @param string $uniqueKey
     * @return void
     */
    public function removeExpiredVisitsFromSession($uniqueKey)
    {
        $currentTime = Carbon::now();
        $sessionItems = Session::get($this->primarySessionKey.'.'.$uniqueKey, []);

        foreach ($sessionItems as $sessionItem) {
            if ($sessionItem['expires_at'] <= $currentTime) {
                $key = array_search($sessionItem['visitable_id'], array_column($sessionItems, 'visitable_id'));

                Session::pull($this->primarySessionKey.'.'.$uniqueKey.'.'.$key);
            }
        }
    }

    /**
     * Convert camel case strings to lowercase dashes.
     *
     * @param string $value
     * @return int The converted string.
     */
    public function fromCamelCaseToDashes($value)
    {
        preg_match_all('!([A-Z][A-Z0-9]*(?=$|[A-Z][a-z0-9])|[A-Za-z][a-z0-9]+)!', $value, $matches);

        $ret = $matches[0];
        foreach ($ret as &$match) {
            $match = $match == strtoupper($match) ? strtolower($match) : lcfirst($match);
        }

        return implode('_', $ret);
    }
}
