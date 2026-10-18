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
use CyrildeWit\EloquentViewable\Support\Chunks;
use CyrildeWit\EloquentViewable\Support\Deadline;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Database\Query\Builder;

/**
 * This action deletes the views viewed before the cutoff. Every view before
 * it goes, not only those after the last run, so a view that landed late is
 * not left behind.
 *
 * Once the deadline passes, the run stops before the next chunk and the mark
 * stays where it was, so the next run deletes the rest.
 */
final readonly class PruneViews
{
    public const string Mark = StateStore::Pruned;

    public function __construct(
        private View $view,
        private RetentionState $state,
        private Watermarks $watermarks,
        private Dispatcher $events,
    ) {}

    /** The same action, clamped to other watermarks. */
    public function withWatermarks(Watermarks $watermarks): self
    {
        return new self($this->view, $this->state, $watermarks, $this->events);
    }

    /** @throws RetentionNotInstalled */
    public function handle(CarbonInterface $cutoff, int $chunk, bool $dryRun = false, ?Deadline $deadline = null): RetentionRun
    {
        $this->state->ensureInstalled();

        $deadline ??= Deadline::none();
        $until = $this->watermarks->clamp($cutoff);
        $from = $this->state->moment(self::Mark);
        $clamped = $until < $cutoff;

        if ($dryRun) {
            return new RetentionRun($from, $until, $this->expired($until)->count(), $clamped, true);
        }

        $progress = Chunks::run(
            $deadline,
            $chunk,
            fn (): array => $this->expiredIds($until, $chunk),
            fn (array $ids): int => $this->view->newQuery()->toBase()->whereIn('id', $ids)->delete(),
        );

        if (! $progress->stopped) {
            $this->moveMarkForward($from, $until);
        }

        if ($progress->count > 0) {
            $this->events->dispatch(new ViewsPruned($from, $until, $progress->count));
        }

        return new RetentionRun($from, $until, $progress->count, $clamped, false, stopped: $progress->stopped);
    }

    /**
     * The ids are selected first, because Postgres has no `delete … limit`
     * and MySQL refuses a limited subquery on the table it deletes from.
     *
     * @return list<int|string>
     */
    private function expiredIds(CarbonInterface $until, int $chunk): array
    {
        /** @var list<int|string> */
        return $this->expired($until)
            ->limit($chunk)
            ->pluck('id')
            ->all();
    }

    private function moveMarkForward(?CarbonInterface $from, CarbonInterface $until): void
    {
        if ($from instanceof CarbonInterface && $from >= $until) {
            return;
        }

        $this->state->putMoment(self::Mark, $until);
    }

    private function expired(CarbonInterface $until): Builder
    {
        return $this->view->newQuery()->toBase()->where('viewed_at', '<', $until);
    }
}
