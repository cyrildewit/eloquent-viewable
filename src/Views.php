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

/**
 * Class Views.
 *
 * @author Cyril de Wit <github@cyrildewit.nl>
 */
class Views
{
    protected $viewable;

    public function __construct($viewable = null)
    {
        $this->viewable = $viewable;
    }

    public static function create($viewable, string $tag): self
    {
        return new static($viewable);
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

        return app(self::class)->countViewsByType($viewableType, $period);
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

        return app(self::class)->countViewsByType($viewableType, $period, true);
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
