<?php

declare(strict_types=1);

/*
 * This file is part of Eloquent Visitable.
 *
 * (c) Cyril de Wit <github@cyrildewit.nl>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace CyrildeWit\EloquentVisitable\Traits;

use Illuminate\Support\ServiceProvider;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use CyrildeWit\EloquentVisitable\Services\VisitService;

/**
 * Make your Eloquent models visitable with this trait.
 *
 * @author Cyril de Wit <github@cyrildewit.nl>
 */
trait Visitable
{
    /**
     * Get a collection of all the visits the model has.
     *
     * @return \Illuminate\Database\Eloquent\Relations\MorphMany
     */
    public function visits(): MorphMany
    {
        return $this->morphMany(
            config('eloquent-visitable.models.visit'),
            'visitable'
        );
    }

    /**
     * Get the total number of visits.
     *
     * @return int
     */
    public function getVisitsCount(): int
    {
        return app(VisitService::class)->getVisitsCount($this);
    }

    /**
     * Get the total number of visits since the given date.
     *
     * @param  \Carbon\Carbon|string  $sinceDate
     * @return int
     */
    public function getVisitsCountSince($sinceDate): int
    {
        return app(VisitService::class)->getVisitsCount($this, $sinceDate);
    }

    /**
     * Get the total number of visits upto the given date.
     *
     * @param  \Carbon\Carbon|string  $uptoDate
     * @return int
     */
    public function getVisitsCountUpto($uptoDate): int
    {
        return app(VisitService::class)->getVisitsCount($this, null, $uptoDate);
    }

    /**
     * Get the total number of visits upto the given date.
     *
     * @param  \Carbon\Carbon|string  $sinceDate
     * @param  \Carbon\Carbon|string  $uptoDate
     * @return int
     */
    public function getVisitsCountBetween($sinceDate, $uptoDate): int
    {
        return app(VisitService::class)->getVisitsCount($this, $sinceDate, $uptoDate);
    }

    /**
     * Get the total number of unique visits.
     *
     * @return int
     */
    public function getUniqueVisitsCount(): int
    {
        return app(VisitService::class)->getUniqueVisitsCount($this);
    }

    /**
     * Get the total number of unique visits since the given date.
     *
     * @param  \Carbon\Carbon|string  $sinceDate
     * @return int
     */
    public function getUniqueVisitsCountSince($sinceDate): int
    {
        return app(VisitService::class)->getUniqueVisitsCount($this, $sinceDate);
    }

    /**
     * Get the total number of unique visits upto the given date.
     *
     * @param  \Carbon\Carbon|string  $uptoDate
     * @return int
     */
    public function getUniqueVisitsCountUpto($uptoDate): int
    {
        return app(VisitService::class)->getUniqueVisitsCount($this, null, $uptoDate);
    }

    /**
     * Get the total number of unique visits upto the given date.
     *
     * @param  \Carbon\Carbon|string  $sinceDate
     * @param  \Carbon\Carbon|string  $uptoDate
     * @return int
     */
    public function getUniqueVisitsCountBetween($sinceDate, $uptoDate): int
    {
        return app(VisitService::class)->getUniqueVisitsCount($this, $sinceDate, $uptoDate);
    }

    /**
     * Store a new visit.
     *
     * @return bool
     */
    public function addVisit(): bool
    {
        return app(VisitService::class)->storeModelVisit($this);
    }
}
