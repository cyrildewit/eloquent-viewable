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

    public static function fromViewable(ViewableContract $viewable)
    {
        return new static($viewable);
    }

    public static function fromViewableType(string $viewableType)
    {
        return new static(null, $viewableType);
    }

    /**
     * Make the cache key.
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
        // Since don't know anything about the connection of the viewable type origin,
        // we need to return an empty string to prevent an exception.
        // TODO: look if an alternative solution could resolve this issue.
        if ($this->viewable === null && $this->viewableType !== null) {
            return '';
        }

        return $this->viewable->getConnection()->getName().':';
    }

    protected function getDatabaseName(): string
    {
        // Since don't know anything about the connection of the viewable type origin,
        // we need to return an empty string to prevent an exception.
        // TODO: look if an alternative solution could resolve this issue.
        if ($this->viewable === null && $this->viewableType !== null) {
            return '';
        }

        return $this->viewable->getConnection()->getDatabaseName().':';
    }

    protected function getTableSlug(): string
    {
        // Since don't know anything about the connection of the viewable type origin,
        // we need to return an empty string to prevent an exception.
        // TODO: look if an alternative solution could resolve this issue.
        if ($this->viewable === null && $this->viewableType !== null) {
            return '';
        }

        return app(Str::class)->slug($this->viewable->getTable()).':';
    }

    protected function getModelSlug(): string
    {
        // TODO: look if an alternative solution could improve this ugluy code
        if ($this->viewable === null && $this->viewableType !== null) {
            return app(Str::class)->slug($this->viewableType).'.';
        }

        return app(Str::class)->slug($this->viewable->getMorphClass()).'.';
    }

    protected function getKeySlug(): string
    {
        // Since don't know anything about the connection of the viewable type origin,
        // we need to return an empty string to prevent an exception.
        // TODO: look if an alternative solution could resolve this issue.
        if ($this->viewable === null && $this->viewableType !== null) {
            return '';
        }

        return $this->viewable->getKey().'.' ?? '';
    }

    protected function getPeriodSlug($period = null): string
    {
        if (! $period) {
            return '|'.'.';
        }

        if ($period && $period->hasFixedDateTimes()) {
            $startDateTime = $period->getStartDateTimestamp();
            $endDateTime = $period->getEndDateTimestamp();

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
        return $collection ? ".{$collection}" : '';
    }
}
