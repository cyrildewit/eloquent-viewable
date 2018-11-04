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

use Illuminate\Database\Eloquent\Model;
use CyrildeWit\EloquentViewable\Support\Period;
use CyrildeWit\EloquentViewable\Contracts\ViewableService as ViewableServiceContract;

/**
 * Class Views.
 *
 * @author Cyril de Wit <github@cyrildewit.nl>
 */
class ViewsOld
{
    /**
     * The subject that has been viewed.
     *
     * @var \Illuminate\Database\Eloquent\Model
     */
    protected $subject;

    /**
     * Create a new views instance.
     *
     * @param  \Illuminate\Database\Eloquent\Model
     */
    public function __construct(Model $subject = null)
    {
        $this->subject = $subject;
    }

    /**
     * Get the total number of views of a viewable type.
     *
     * @param string|Illuminate\Database\Eloquent\Model  $viewableType
     * @param  \CyrildeWit\EloquentViewable\Support\Period
     * @return int
     */
    public static function getViewsByType($viewableType, $period = null): int
    {
        if ($viewableType instanceof Model) {
            $viewableType = $viewableType->getMorphClass();
        }

        return (new static)->countViewsByType($viewableType, $period);
    }

    /**
     * Get the total number of unique views of a viewable type.
     *
     * @param string|Illuminate\Database\Eloquent\Model  $viewableType
     * @param  \CyrildeWit\EloquentViewable\Support\Period
     * @return int
     */
    public static function getUniqueViewsByType($viewableType, $period = null): int
    {
        if ($viewableType instanceof Model) {
            $viewableType = $viewableType->getMorphClass();
        }

        return (new static)->countViewsByType($viewableType, $period, true);
    }

    public static function getMostViewedByType($viewableType, int $limit = 10)
    {
        return app(ViewableServiceContract::class)
            ->applyScopeOrderByViewsCount($this->viewable->query(), 'desc')
            ->take($limit)->count();
    }

    /**
     * Get the total number of views.
     *
     * @param  \CyrildeWit\EloquentViewable\Support\Period
     * @return int
     */
    public function getViews($period = null): int
    {
        return app(ViewableServiceContract::class)
            ->getViewsCount($this->viewable, $period);
    }

    /**
     * Get the total number of unique views.
     *
     * @param  \CyrildeWit\EloquentViewable\Support\Period
     * @return int
     */
    public function getUniqueViews($period = null): int
    {
        return app(ViewableServiceContract::class)
            ->getUniqueViewsCount($this->viewable, $period);
    }

    /**
     * Record a view.
     *
     * @return bool @todo
     */
    public function record(): bool
    {
        return app(ViewableServiceContract::class)->addViewTo($this->subject);
    }

    /**
     * Store a new view with an expiry date.
     *
     * @param  \DateTime  $expiresAt
     * @return bool
     */
    public function addViewWithExpiryDate($expiresAt): bool
    {
        return app(ViewableServiceContract::class)
            ->addViewWithExpiryDateTo($this->viewable, $expiresAt);
    }

    /**
     * Count the views of a specific viewable type.
     *
     * @param  \Illuminate\Database\Eloquent\Model  $viewable
     * @param  \CyrildeWit\EloquentViewable\Support\Period
     * @param  bool  $unique
     * @return int
     */
    protected function countViewsByType($viewableType, $period, bool $unique = false)
    {
        // Use inserted period, otherwise create an empty one
        $period = $period ?? Period::create();

        $startDateTime = $period->getStartDateTime();
        $endDateTime = $period->getEndDateTime();

        // Create new Query Builder instance of the views table
        $query = View::where('viewable_type', $viewableType);

        // Apply the following date filters
        if ($startDateTime && ! $endDateTime) {
            $query->where('viewed_at', '>=', $startDateTime);
        } elseif (! $startDateTime && $endDateTime) {
            $query->where('viewed_at', '<=', $endDateTime);
        } elseif ($startDateTime && $endDateTime) {
            $query->whereBetween('viewed_at', [$startDateTime, $endDateTime]);
        }

        // Count all the views
        if (! $unique) {
            $viewsCount = $query->count();
        }

        // Count only the unique views
        if ($unique) {
            $viewsCount = $query->distinct('visitor')->count('visitor');
        }

        return $viewsCount;
    }
}
