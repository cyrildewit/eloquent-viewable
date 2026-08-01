<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable;

use CyrildeWit\EloquentViewable\Contracts\Viewable;
use CyrildeWit\EloquentViewable\Support\Period;
use Illuminate\Container\Container;
use Illuminate\Support\Str;

class CacheKey
{
    public function __construct(protected Viewable $viewable) {}

    public static function fromViewable(Viewable $viewable): CacheKey
    {
        return new self($viewable);
    }

    public function make(?Period $period = null, bool $unique = false, ?string $collection = null): string
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

        return $key.$this->getCollectionSlug($collection);
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

        return $this->viewable->getKey().'.';
    }

    protected function getPeriodSlug(?Period $period = null): string
    {
        if (! $period instanceof Period) {
            return '|.';
        }

        $signature = $period->cacheSignature();

        if ($signature !== null) {
            return "{$signature}|".'.';
        }

        $startDateTime = $period->getStartDateTime()?->timestamp;
        $endDateTime = $period->getEndDateTime()?->timestamp;

        return "{$startDateTime}|{$endDateTime}".'.';
    }

    protected function getUniqueSlug(bool $unique = false): string
    {
        return $unique ? 'unique' : 'normal';
    }

    protected function getCollectionSlug(?string $collection = null): string
    {
        return $collection ? ".{$collection}" : '';
    }
}
