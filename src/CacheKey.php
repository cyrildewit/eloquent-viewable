<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable;

use CyrildeWit\EloquentViewable\Contracts\Viewable;
use Illuminate\Container\Container;
use Illuminate\Support\Str;

class CacheKey
{
    /** @var \CyrildeWit\EloquentViewable\Contracts\Viewable|null */
    protected $viewable;

    /** @var string|null */
    protected $viewableType;

    /**
     * Create a new cache key instance.
     *
     * @param  \CyrildeWit\EloquentViewable\Contracts\Viewable|null  $viewable
     * @return void
     */
    public function __construct(Viewable $viewable = null)
    {
        $this->viewable = $viewable;
    }

    public static function fromViewable(Viewable $viewable)
    {
        return new static($viewable);
    }

    /**
     * Make the cache key.
     *
     * @param  \CyrildeWit\EloquentViewable\Support\Period|null  $period
     * @param  bool  $unique
     * @param  string|null  $collection
     * @return string
     */
    public function make($period = null, bool $unique = false, string $collection = null)
    {
        $key = $this->getCachePrefix();
        $key .= $this->getConnectionName();
        $key .= $this->getDatabaseName();
        $key .= $this->getViewableTypeSlug();
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
        return Container::getInstance()
            ->make('config')
            ->get('eloquent-viewable.cache.key').':';
    }

    protected function getConnectionName(): string
    {
        return $this->viewable->getConnection()->getName().':';
    }

    protected function getDatabaseName(): string
    {
        return $this->viewable->getConnection()->getDatabaseName().':';
    }

    protected function getViewableTypeSlug(): string
    {
        if ($this->viewable->getKey() === null) {
            return 'type.';
        }

        return '';
    }

    protected function getTableSlug(): string
    {
        return Str::slug($this->viewable->getTable()).':';
    }

    protected function getModelSlug(): string
    {
        return Str::slug($this->viewable->getMorphClass()).'.';
    }

    protected function getKeySlug(): string
    {
        if ($this->viewable->getKey() === null) {
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
