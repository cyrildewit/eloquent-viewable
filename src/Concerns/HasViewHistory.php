<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Concerns;

use Carbon\Carbon;
use Carbon\CarbonInterface;
use CyrildeWit\EloquentViewable\Contracts\Viewable;
use CyrildeWit\EloquentViewable\Models\View;
use CyrildeWit\EloquentViewable\Support\Config;
use CyrildeWit\EloquentViewable\Support\Period;
use CyrildeWit\EloquentViewable\Support\ViewsQuery;
use Illuminate\Container\Container;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * Not named views(), because a model can be viewable and a viewer at once
 * and InteractsWithViews owns that name.
 */
trait HasViewHistory
{
    /** @return MorphMany<View, $this> */
    public function viewed(): MorphMany
    {
        return $this->morphMany(
            Container::getInstance()->make(Config::class)->viewModel(),
            'viewer'
        )->latest('viewed_at');
    }

    public function hasViewed(Viewable $viewable, ?Period $period = null, ?string $collection = null): bool
    {
        return $this->viewed()
            ->forViewable($viewable)
            ->matching(new ViewsQuery($period, $collection))
            ->exists();
    }

    public function lastViewedAt(Viewable $viewable, ?string $collection = null): ?CarbonInterface
    {
        $viewedAt = $this->viewed()
            ->forViewable($viewable)
            ->matching(new ViewsQuery(collection: $collection))
            ->max('viewed_at');

        return is_string($viewedAt) ? Carbon::parse($viewedAt) : null;
    }
}
