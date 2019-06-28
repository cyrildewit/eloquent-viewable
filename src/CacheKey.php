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

use Illuminate\Support\Str;
use CyrildeWit\EloquentViewable\Support\Period;
use CyrildeWit\EloquentViewable\Contracts\Viewable as ViewableContract;

class CacheKey
{
    /** @var \CyrildeWit\EloquentViewable\Contracts\Viewable|null */
    protected $viewable;

    /** @var string|null */
    protected $viewableType;

    /**
     * Create a new cache key instance.
     *
     * @return void
     */
    public function __construct(
        ViewableContract $viewable = null,
        string $viewableType = null
    ) {
        $this->viewable = $viewable;
        $this->viewableType = $viewableType;
    }

    /**
     * Make a the cache key.
     *
     * @param  \CyrildeWit\EloquentViewable\Support\Period|null  $period
     * @param  bool  $unique
     */
    public function make($period = null, bool $unique = false, string $collection = null)
    {
        $key = $this->getCachePrefix();
        $key .= $this->getConnectionName();
        $key .= $this->getDatabaseName();
        $key .= $this->getTableSlug();
        $key .= $this->getModelSlug();
        $key .= $this->getKeySlug();
        $key .= $this->getPeriodSlug($period);
        $key .= $this->getUniqueSlug($unique);
        $key .= $this->getCollectionSlug($collection);

        return $key;
    }

    protected function getCachePrefix(): string
    {
        return config('eloquent-viewable.cache.key', 'cyrildewit.eloquent-viewable.cache').':';
    }

    protected function getConnectionName(): string
    {
        return $this->viewable->getConnection()->getName().':';
    }

    protected function getDatabaseName(): string
    {
        return $this->viewable->getConnection()->getDatabaseName().':';
    }

    protected function getTableSlug(): string
    {
        return app(Str::class)->slug($this->viewable->getTable()).':';
    }

    protected function getModelSlug(): string
    {
        return app(Str::class)->slug($this->viewable->getMorphClass()).'.';
    }

    protected function getKeySlug()
    {
        return $this->viewable->getKey().'.' ?? '';
    }

    protected function getPeriodSlug($period = null): string
    {
        if (! $period) {
            return '|'.'.';
        }

        if ($period && $period->hasFixedDateTimes()) {
            $startDateTime = '';
            $endDateTime = '';

            try {
                $startDateTime = $period->getStartDateTimestamp();
            } catch (\Exception  $th) {
            }

            try {
                $endDateTime = $period->getEndDateTimestamp();
            } catch (\Exception  $th) {
            }

            return "{$startDateTime}|{$endDateTime}".'.';
        }

        [$subType, $subValueType] = explode('_', strtolower($period->getSubType()));

        return "{$subType}{$period->getSubValue()}{$subValueType}|".'.';
    }

    protected function getUniqueSlug($unique = false): string
    {
        return $unique ? 'unique' : 'normal';
    }

    protected function getCollectionSlug($collection = null): string
    {
        return $collection ? ".{$collection}" : '';;
    }
}
