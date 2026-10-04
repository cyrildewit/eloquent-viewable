<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Retention\Actions;

use Carbon\CarbonInterface;
use CyrildeWit\EloquentViewable\Models\View;
use CyrildeWit\EloquentViewable\Querying\Rollups\Contracts\StateStore;
use CyrildeWit\EloquentViewable\Querying\Rollups\Contracts\Watermarks;
use CyrildeWit\EloquentViewable\Retention\Data\RetentionRun;
use CyrildeWit\EloquentViewable\Retention\Events\ViewsPruned;
use CyrildeWit\EloquentViewable\Retention\Exceptions\RetentionNotInstalled;
use CyrildeWit\EloquentViewable\Retention\State\RetentionState;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Database\Query\Builder;

/**
 * Deletes the views viewed before the cutoff. Every view before it goes, not
 * only those after the last run, so a view that landed late is not left
 * behind.
 */
final readonly class PruneViews
{
    public const string MARK = StateStore::PRUNED;

    public function __construct(
        private View $view,
        private RetentionState $state,
        private Watermarks $watermarks,
        private Dispatcher $events,
    ) {}

    /** @throws RetentionNotInstalled */
    public function handle(CarbonInterface $cutoff, int $chunk, bool $dryRun = false): RetentionRun
    {
        $this->state->ensureInstalled();

        $until = $this->watermarks->clamp($cutoff);
        $from = $this->state->moment(self::MARK);
        $clamped = $until < $cutoff;

        if ($dryRun) {
            return new RetentionRun($from, $until, $this->expired($until)->count(), $clamped, true);
        }

        $views = 0;

        do {
            // Postgres has no `delete … limit`, and MySQL refuses a limited
            // subquery on the table it deletes from, so the ids come first.
            $ids = $this->expired($until)->limit($chunk)->pluck('id')->all();

            if ($ids !== []) {
                $views += $this->view->newQuery()->toBase()->whereIn('id', $ids)->delete();
            }
        } while (count($ids) === $chunk);

        if (! $from instanceof CarbonInterface || $from < $until) {
            $this->state->putMoment(self::MARK, $until);
        }

        if ($views > 0) {
            $this->events->dispatch(new ViewsPruned($from, $until, $views));
        }

        return new RetentionRun($from, $until, $views, $clamped, false);
    }

    private function expired(CarbonInterface $until): Builder
    {
        return $this->view->newQuery()->toBase()->where('viewed_at', '<', $until);
    }
}
