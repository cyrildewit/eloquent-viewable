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
use CyrildeWit\EloquentViewable\Contracts\Viewable as ViewableContract;

class ViewSessionHistory
{
    /**
     * The session repository instance.
     *
     * @var \Illuminate\Contracts\Session\Repository
     */
    protected $session;

    /**
     * The primary key under which history is stored.
     *
     * @var string
     */
    protected $primaryKey;

    /**
     * Create a new view session history instance.
     *
     * @return void
     */
    public function __construct(Session $session)
    {
        $this->session = $session;
        $this->primaryKey = config('eloquent-viewable.session.key', 'cyrildewit.eloquent-viewable.session');
    }

    /**
     * Push a viewable model with an expiry date to the session.
     *
     * @param  \CyrildeWit\EloquentViewable\Contracts\Viewable  $viewable
     * @param  \DateTime  $expiryDateTime
     * @param  string  $collection
     * @return bool
     */
    public function push(ViewableContract $viewable, $delay, string $collection = null): bool
    {
        $namespaceKey = $this->createNamespaceKey($viewable, $collection);
        $viewableKey = $this->createViewableKey($viewable, $collection);

        $this->forgetExpiredViews($namespaceKey);

        if (! $this->has($viewableKey)) {
            $this->session->put($viewableKey, $this->createRecord($viewable, $delay));

            return true;
        }

        return false;
    }

    /**
     * Determine if the given model has been viewed.
     *
     * @param  string  $key
     * @return bool
     */
    protected function has(string $viewableKey): bool
    {
        return $this->session->has($viewableKey);
    }

    /**
     * Create a history record from the given viewable model and expiry date.
     *
     * @param  \CyrildeWit\EloquentViewable\Contracts\Viewable  $viewable
     * @param  \DateTime  $expiryDateTime
     * @return array
     */
    protected function createRecord(ViewableContract $viewable, $expiryDateTime): array
    {
        return [
            'viewable_id' => $viewable->getKey(),
            'expires_at' => $expiryDateTime,
        ];
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
            if ($record['expires_at']->lte($currentTime)) {
                $recordId = array_search($record['viewable_id'], array_column($record, 'viewable_id'));

                $this->session->pull($key.$recordId);
            }
        }
    }

    /**
     * Create a base key from the given viewable model.
     *
     * Returns for example:
     * => `eloquent-viewable.session.key.app-models-post`
     *
     * @param  \CyrildeWit\EloquentViewable\Contracts\Viewable  $viewable
     * @return string
     */
    protected function createNamespaceKey(ViewableContract $viewable, string $collection = null): string
    {
        $key = $this->primaryKey;
        $key .= '.'.strtolower(str_replace('\\', '-', $viewable->getMorphClass()));
        $key .= is_string($collection) ? ":{$collection}" : '';

        return $key;
    }

    /**
     * Create a unique key from the given viewable model.
     *
     * Returns for example:
     * => `eloquent-viewable.session.key.app-models-post.1`
     *
     * @param  \CyrildeWit\EloquentViewable\Contracts\Viewable  $viewable
     * @param  string  $collection
     * @return string
     */
    protected function createViewableKey(ViewableContract $viewable, string $collection = null): string
    {
        $key = $this->createNamespaceKey($viewable, $collection);
        $key .= ".{$viewable->getKey()}";

        return $key;
    }
}
