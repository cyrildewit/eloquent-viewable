<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Concerns;

use Carbon\Carbon;
use Carbon\CarbonInterface;
use CyrildeWit\EloquentViewable\Contracts\Viewable;
use CyrildeWit\EloquentViewable\Erasure\Actions\AnonymiseViewHistory;
use CyrildeWit\EloquentViewable\Erasure\Actions\ExportViewHistory;
use CyrildeWit\EloquentViewable\Erasure\Actions\ForgetViewHistory;
use CyrildeWit\EloquentViewable\Erasure\Subject;
use CyrildeWit\EloquentViewable\Exceptions\InvalidConfiguration;
use CyrildeWit\EloquentViewable\Exceptions\InvalidTimezone;
use CyrildeWit\EloquentViewable\Exceptions\InvalidViewer;
use CyrildeWit\EloquentViewable\Models\View;
use CyrildeWit\EloquentViewable\Support\Config;
use CyrildeWit\EloquentViewable\Support\Period;
use CyrildeWit\EloquentViewable\Support\ViewsQuery;
use Illuminate\Container\Container;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\LazyCollection;

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

    /**
     * Delete every view this model made, and with guest views also the views
     * of the browsers it was signed in on. Returns how many were deleted.
     *
     * @throws InvalidViewer
     */
    public function forgetViewHistory(bool $includeGuestViews = false): int
    {
        return Container::getInstance()->make(ForgetViewHistory::class)->handle(Subject::viewer($this), $includeGuestViews);
    }

    /**
     * Keep the views this model made in the counts, but take what ties them
     * to it out. Returns how many were anonymised.
     *
     * @throws InvalidConfiguration
     * @throws InvalidTimezone
     * @throws InvalidViewer
     */
    public function anonymiseViewHistory(): int
    {
        return Container::getInstance()->make(AnonymiseViewHistory::class)->handle(Subject::viewer($this));
    }

    /**
     * Read every view this model made, oldest first, for a data access request.
     *
     * @return LazyCollection<int, array{viewable_type: string, viewable_id: int|string, collection: ?string, context: ?array<string, mixed>, viewed_at: string}>
     *
     * @throws InvalidViewer
     */
    public function exportViewHistory(): LazyCollection
    {
        return Container::getInstance()->make(ExportViewHistory::class)->handle(Subject::viewer($this));
    }
}
