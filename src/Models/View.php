<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Models;

use Carbon\CarbonInterface;
use CyrildeWit\EloquentViewable\Contracts\Viewable;
use CyrildeWit\EloquentViewable\Database\Factories\ViewFactory;
use CyrildeWit\EloquentViewable\Exceptions\InvalidViewer;
use CyrildeWit\EloquentViewable\Support\Config;
use CyrildeWit\EloquentViewable\Support\Period;
use CyrildeWit\EloquentViewable\Support\ViewerKey;
use CyrildeWit\EloquentViewable\Support\ViewsQuery;
use Illuminate\Container\Container;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * @property int $id
 * @property string $viewable_type
 * @property int|string $viewable_id
 * @property ?string $viewer_type
 * @property int|string|null $viewer_id
 * @property ?string $visitor
 * @property ?string $collection
 * @property ?array<string, mixed> $context
 * @property string $viewed_at
 */
class View extends Model
{
    /** @use HasFactory<ViewFactory> */
    use HasFactory;

    #[\Override]
    protected $guarded = [];

    #[\Override]
    public $timestamps = false;

    /** @return array<string, string> */
    #[\Override]
    protected function casts(): array
    {
        return [
            'context' => 'array',
        ];
    }

    #[\Override]
    public function getTable(): string
    {
        return $this->table ?? $this->config()->viewTable() ?? parent::getTable();
    }

    #[\Override]
    public function getConnectionName(): ?string
    {
        return parent::getConnectionName() ?? $this->config()->viewConnection();
    }

    private function config(): Config
    {
        return Container::getInstance()->make(Config::class);
    }

    /**
     * The factory behind `View::factory()`. A model that extends this one
     * inherits it and gets instances of its own class back.
     */
    protected static function newFactory(): ViewFactory
    {
        return ViewFactory::new()->forModel(static::class);
    }

    /** @return MorphTo<Model, $this> */
    public function viewable(): MorphTo
    {
        return $this->morphTo();
    }

    /** @return MorphTo<Model, $this> */
    public function viewer(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Build a query for the views of the viewable that match the views query.
     *
     * @return Builder<static>
     */
    public function newQueryFor(Viewable $viewable, ViewsQuery $viewsQuery): Builder
    {
        return $this->newQuery()->forViewable($viewable)->matching($viewsQuery);
    }

    /**
     * Scope a query to only include views within the period. The period is
     * half-open: the start is included and the end is excluded.
     *
     * @param  Builder<View>  $query
     */
    public function scopeWithinPeriod(Builder $query, Period $period): void
    {
        $startDateTime = $period->getStartDateTime();
        $endDateTime = $period->getEndDateTime();

        if ($startDateTime instanceof CarbonInterface) {
            $query->where('viewed_at', '>=', $startDateTime);
        }

        if ($endDateTime instanceof CarbonInterface) {
            $query->where('viewed_at', '<', $endDateTime);
        }
    }

    /** @param  Builder<View>  $query */
    public function scopeCollection(Builder $query, ?string $collection = null): void
    {
        $query->where('collection', $collection);
    }

    /**
     * Scope a query to only include views of the viewable. A viewable without
     * a key stands for every viewable of its type.
     *
     * @param  Builder<View>  $query
     */
    public function scopeForViewable(Builder $query, Viewable $viewable): void
    {
        $query->where('viewable_type', $viewable->getMorphClass());

        if ($viewable->getKey() !== null) {
            $query->where('viewable_id', $viewable->getKey());
        }
    }

    /**
     * @param  Builder<View>  $query
     *
     * @throws InvalidViewer
     */
    public function scopeByViewer(Builder $query, Model $viewer): void
    {
        $query
            ->where($this->qualifyColumn('viewer_type'), $viewer->getMorphClass())
            ->where($this->qualifyColumn('viewer_id'), ViewerKey::of($viewer));
    }

    /** @param  Builder<View>  $query */
    public function scopeByVisitor(Builder $query, string $visitor): void
    {
        $query->where($this->qualifyColumn('visitor'), $visitor);
    }

    /**
     * Scope a query to only include views matching the period, collection,
     * viewer, filter and dimensions of the views query. Uniqueness is an
     * aggregate choice rather than a filter, so the caller applies it.
     *
     * @param  Builder<View>  $query
     */
    public function scopeMatching(Builder $query, ViewsQuery $viewsQuery): void
    {
        if ($viewsQuery->period instanceof Period) {
            $query->withinPeriod($viewsQuery->period);
        }

        if ($viewsQuery->collection !== null) {
            $query->collection($viewsQuery->collection);
        }

        if ($viewsQuery->viewer instanceof Model) {
            $query->byViewer($viewsQuery->viewer);
        }

        $viewsQuery->filter?->filter($query);

        foreach ($viewsQuery->dimensions as $filter) {
            $query->whereIn($this->qualifyColumn($filter->target), $filter->values);
        }
    }
}
