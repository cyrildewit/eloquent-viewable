<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable;

use CyrildeWit\EloquentViewable\Contracts\View as ViewContract;
use CyrildeWit\EloquentViewable\Support\Period;
use Illuminate\Container\Container;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class View extends Model implements ViewContract
{
    /**
     * The attributes that aren't mass assignable.
     *
     * @var array
     */
    protected $guarded = [];

    /**
     * Indicates if the model should be timestamped.
     *
     * @var bool
     */
    public $timestamps = false;

    /**
     * Get the table associated with the model.
     *
     * @return string
     */
    public function getTable()
    {
        return Container::getInstance()
            ->make('config')
            ->get('eloquent-viewable.models.view.table_name', parent::getTable());
    }

    /**
     * Get the current connection name for the model.
     *
     * @return string
     */
    public function getConnectionName()
    {
        return Container::getInstance()
            ->make('config')
            ->get('eloquent-viewable.models.view.connection', parent::getConnectionName());
    }

    /**
     * Get the viewable model to which this View belongs.
     *
     * @return \Illuminate\Database\Eloquent\Relations\MorphTo
     */
    public function viewable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Scope a query to only include views within the period.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @param  \CyrildeWit\EloquentViewable\Support\Period  $period
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeWithinPeriod(Builder $query, Period $period)
    {
        $startDateTime = $period->getStartDateTime();
        $endDateTime = $period->getEndDateTime();

        if ($startDateTime !== null && $endDateTime === null) {
            $query->where('viewed_at', '>=', $startDateTime);
        } elseif ($startDateTime === null && $endDateTime !== null) {
            $query->where('viewed_at', '<=', $endDateTime);
        } elseif ($startDateTime !== null && $endDateTime !== null) {
            $query->whereBetween('viewed_at', [$startDateTime, $endDateTime]);
        }

        return $query;
    }

    /**
     * Scope a query to only include views withing the collection.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @param  string|null  $collection
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeCollection(Builder $query, string $collection = null)
    {
        return $query->where('collection', $collection);
    }
}
