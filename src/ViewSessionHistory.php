<?php

declare(strict_types=1);

/*
 * This file is part of the Eloquent Viewable package.
 *
 * (c) Cyril de Wit <github@cyrildewit.nl>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace CyrildeWit\EloquentViewable;

use Carbon\Carbon;
use Illuminate\Contracts\Session\Session;

/**
 * Class ViewSessionHistory.
 *
 * @author Cyril de Wit <github@cyrildewit.nl>
 */
class ViewSessionHistory
{
    /**
     * The session repository instance.
     *
     * @var \Illuminate\Contracts\Session\Repository
     */
    protected $session;

    /**
     * @var string
     */
    protected $primaryKey;

    /**
     * Create a new view session history instance.
     */
    public function __construct()
    {
        $this->session = app(Session::class);
        $this->primaryKey = config('eloquent-viewable.session.key', 'cyrildewit.eloquent-viewable.session');
    }

    /**
     * Push a viewable model with an expiry date to the session.
     *
     * @param  \Illuminate\Database\Eloquent\Model  $viewable
     * @param  \DateTime  $expiryDateTime
     */
    public function push($viewable, $expiryDateTime): bool
    {
        $baseKey = $this->createBaseKey($viewable);
        $uniqueKey = $this->createUniqueKey($viewable);

        $this->forgetExpiredViews($baseKey);

        if (! $this->isViewableViewed($uniqueKey)) {
            $this->session->put($uniqueKey, $this->createRecord($viewable, $expiryDateTime));

            return true;
        }

        return false;
    }

    /**
     * Create a history record from the given viewable model and expiry date.
     *
     * @param  \Illuminate\Database\Eloquent\Model  $viewable
     * @param  \DateTime  $expiryDateTime
     * @return array
     */
    protected function createRecord($viewable, $expiryDateTime): array
    {
        return [
            'viewable_id' => $viewable->getKey(),
            'expires_at' => $expiryDateTime,
        ];
    }

    /**
     * Determine if the given model has been viewed.
     *
     * @param  string  $key
     * @return bool
     */
    protected function isViewableViewed(string $uniqueKey): bool
    {
        return $this->session->has($uniqueKey);
    }

    /**
     * Remove all expired views from the session.
     *
     * @param  string  $key
     * @return void
     */
    protected function forgetExpiredViews(string $key)
    {
        $currentTime = Carbon::now();
        $viewHistory = $this->session->get($key, []);

        foreach ($viewHistory as $record) {
            // Less thatn or equal to
            if ($record['expires_at']->lte($currentTime)) {
                $recordId = array_search($record['viewable_id'], array_column($record, 'viewable_id'));

                $this->session->pull($key.$recordId);
            }
        }
    }

    /**
     * Create a base key from the given viewable model.
     *
     * @param  \Illuminate\Database\Eloquent\Model  $viewable
     * @return string
     */
    protected function createBaseKey($viewable): string
    {
        return $this->primaryKey.'.'.strtolower(str_replace('\\', '-', $viewable->getMorphClass()));
    }

    /**
     * Create a unique key from the given viewable model.
     *
     * @param  \Illuminate\Database\Eloquent\Model  $viewable
     * @return string
     */
    protected function createUniqueKey($viewable): string
    {
        return $this->createBaseKey($viewable).'.'.$viewable->getKey();
    }
}
