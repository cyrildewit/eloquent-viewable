<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable;

use Carbon\CarbonInterface;
use CyrildeWit\EloquentViewable\Contracts\View as ViewContract;
use CyrildeWit\EloquentViewable\Support\Period;
use Illuminate\Container\Container;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class View extends Model implements ViewContract
{
    #[\Override]
    protected $guarded = [];

    #[\Override]
    public $timestamps = false;

    #[\Override]
    public function getTable()
    {
        return Container::getInstance()
            ->make('config')
            ->get('eloquent-viewable.models.view.table_name', parent::getTable());
    }

    #[\Override]
    public function getConnectionName()
    {
        return Container::getInstance()
            ->make('config')
            ->get('eloquent-viewable.models.view.connection', parent::getConnectionName());
    }

    public function viewable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Scope a query to only include views within the period.
     */
    public function scopeWithinPeriod(Builder $query, Period $period): void
    {
        $startDateTime = $period->getStartDateTime();
        $endDateTime = $period->getEndDateTime();

        if ($startDateTime instanceof CarbonInterface && ! $endDateTime instanceof CarbonInterface) {
            $query->where('viewed_at', '>=', $startDateTime);
        } elseif (! $startDateTime instanceof CarbonInterface && $endDateTime instanceof CarbonInterface) {
            $query->where('viewed_at', '<=', $endDateTime);
        } elseif ($startDateTime instanceof CarbonInterface && $endDateTime instanceof CarbonInterface) {
            $query->whereBetween('viewed_at', [$startDateTime, $endDateTime]);
        }
    }

    /**
     * Scope a query to only include views withing the collection.
     */
    public function scopeCollection(Builder $query, ?string $collection = null): void
    {
        $query->where('collection', $collection);
    }
}
