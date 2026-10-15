<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Samples\ContentDashboard;

use CyrildeWit\EloquentViewable\Querying\Comparison\ViewComparison;
use CyrildeWit\EloquentViewable\Querying\Ranking\Entry;
use CyrildeWit\EloquentViewable\Querying\Ranking\Ranking;
use CyrildeWit\EloquentViewable\Support\Period;
use Illuminate\Database\Eloquent\Collection;
use JsonSerializable;

final readonly class DashboardReport implements JsonSerializable
{
    public function __construct(
        public Period $period,
        /** The most viewed guides and episodes, ranked together. */
        public Ranking $top,
        /** @var array<string, ?ViewComparison> each type against the period before, null when the period is open */
        public array $trends,
        /** @var array<string, int> views per placement, `direct` for views without one */
        public array $placements,
        /** @var Collection<int, Guide> */
        public Collection $latestGuides,
        /** @var array<int|string, int> views of each of the latest guides, keyed by id */
        public array $latestGuideViews,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return [
            'period' => $this->period->getRouteKey(),
            'top' => $this->top->entries->map(fn (Entry $entry): array => [
                'rank' => $entry->rank,
                'type' => class_basename($entry->viewable),
                'title' => $entry->viewable->getAttribute('title'),
                'views' => $entry->count,
            ])->all(),
            'trends' => $this->trends,
            'placements' => $this->placements,
            'latest_guides' => $this->latestGuides->map(fn (Guide $guide): array => [
                'title' => $guide->title,
                'views' => $this->latestGuideViews[$guide->id],
            ])->all(),
        ];
    }
}
