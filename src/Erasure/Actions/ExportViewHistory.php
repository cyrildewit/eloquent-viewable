<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Erasure\Actions;

use CyrildeWit\EloquentViewable\Erasure\Events\ViewHistoryExported;
use CyrildeWit\EloquentViewable\Erasure\Subject;
use CyrildeWit\EloquentViewable\Erasure\ViewHistory;
use CyrildeWit\EloquentViewable\Models\View;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Support\Carbon;
use Illuminate\Support\LazyCollection;

/**
 * This action reads every view of a subject for a data access request,
 * oldest first. The visitor id is left out, because it is a pseudonym that
 * means nothing to the person. The views are read as the collection is
 * iterated, so a long history never sits in memory whole.
 */
final readonly class ExportViewHistory
{
    public function __construct(
        private ViewHistory $history,
        private Dispatcher $events,
    ) {}

    /** @return LazyCollection<int, array{viewable_type: string, viewable_id: int|string, collection: ?string, context: ?array<string, mixed>, viewed_at: string}> */
    public function handle(Subject $subject, int $chunk = 1000): LazyCollection
    {
        $selection = $this->history->select($subject);

        $this->history->land($selection);

        $this->events->dispatch(new ViewHistoryExported($subject));

        return $this->history
            ->query($selection)
            ->lazyById($chunk)
            ->map(fn (View $view): array => [
                'viewable_type' => $view->viewable_type,
                'viewable_id' => $view->viewable_id,
                'collection' => $view->collection,
                'context' => $view->context,
                'viewed_at' => Carbon::parse($view->viewed_at)->toIso8601String(),
            ]);
    }
}
