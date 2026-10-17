<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Erasure\Actions;

use CyrildeWit\EloquentViewable\Erasure\Events\ViewHistoryForgotten;
use CyrildeWit\EloquentViewable\Erasure\Subject;
use CyrildeWit\EloquentViewable\Erasure\TouchedViewables;
use CyrildeWit\EloquentViewable\Erasure\ViewHistory;
use CyrildeWit\EloquentViewable\Models\View;
use CyrildeWit\EloquentViewable\Support\Chunks;
use CyrildeWit\EloquentViewable\Support\Deadline;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Database\Eloquent\Collection;

/**
 * This action deletes every view of a subject, buffered or stored, and
 * forgets and recounts the counts that included them. Rollups hold no one's
 * identity, so the history they keep is left as it is.
 *
 * With guest views included, the views carrying a visitor id the viewer
 * recorded under go too, which on a shared browser are someone else's.
 */
final readonly class ForgetViewHistory
{
    public function __construct(
        private ViewHistory $history,
        private View $view,
        private Dispatcher $events,
    ) {}

    public function handle(Subject $subject, bool $includeGuestViews = false, int $chunk = 1000): int
    {
        $selection = $this->history->select($subject);

        $this->history->land($selection);

        if ($includeGuestViews) {
            $selection = $selection->withVisitors($this->history->visitorsOf($selection));

            $this->history->land($selection);
        }

        $touched = new TouchedViewables;

        $views = Chunks::run(
            Deadline::none(),
            $chunk,
            fn (): Collection => $this->history
                ->query($selection)
                ->orderBy('id')
                ->limit($chunk)
                ->get(['id', 'viewable_type', 'viewable_id']),
            function (Collection $rows) use ($touched): int {
                foreach ($rows as $row) {
                    $touched->add($row->viewable_type, $row->viewable_id);
                }

                $this->view->newQuery()->toBase()->whereIn('id', $rows->modelKeys())->delete();

                return $rows->count();
            },
        )->count;

        $this->events->dispatch($touched->countsChanged());

        $this->events->dispatch(new ViewHistoryForgotten($subject, $views));

        return $views;
    }
}
