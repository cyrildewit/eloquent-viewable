<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Erasure;

use CyrildeWit\EloquentViewable\Models\View;
use CyrildeWit\EloquentViewable\Recording\Contracts\BufferedViewStore;
use CyrildeWit\EloquentViewable\Recording\Contracts\ViewStore;
use CyrildeWit\EloquentViewable\Retention\Actions\AnonymiseViews;
use CyrildeWit\EloquentViewable\Visitors\VisitorIdentity;
use Illuminate\Database\Eloquent\Builder;

/**
 * The views of a subject, whichever store they wait in.
 *
 * @internal
 */
final readonly class ViewHistory
{
    public function __construct(
        private View $view,
        private ViewStore $store,
        private VisitorIdentity $identity,
    ) {}

    /**
     * A viewer is matched by its columns and by the visitor id derived from
     * it under the `viewer` and `fingerprint` identities, which outlives
     * detaching the viewer columns by hand.
     */
    public function select(Subject $subject): Selection
    {
        if ($subject->viewerType !== null && $subject->viewerKey !== null) {
            return Selection::viewer(
                $subject->viewerType,
                $subject->viewerKey,
                $this->identity->ofViewerKey($subject->viewerType, $subject->viewerKey),
            );
        }

        return Selection::visitor((string) $subject->visitor);
    }

    /**
     * A buffer cannot be queried, so the views of the selection are landed
     * first and every step after works on the views table alone.
     */
    public function land(Selection $selection): void
    {
        if (! $this->store instanceof BufferedViewStore) {
            return;
        }

        $this->store->land($selection->matches(...));
    }

    /** @return Builder<View> */
    public function query(Selection $selection): Builder
    {
        return $selection->constrain($this->view->newQuery());
    }

    /**
     * The visitor ids a viewer recorded its views under, which its views as
     * a guest share. Anonymised ids lead to no one and are left out.
     *
     * @return list<string>
     */
    public function visitorsOf(Selection $selection): array
    {
        if ($selection->viewerType === null) {
            return [];
        }

        $anonymised = AnonymiseViews::Prefix;

        $visitors = $this->view
            ->newQuery()
            ->toBase()
            ->where('viewer_type', $selection->viewerType)
            ->where('viewer_id', $selection->viewerKey)
            ->whereNotNull('visitor')
            ->where('visitor', 'not like', "{$anonymised}%")
            ->distinct()
            ->pluck('visitor')
            ->all();

        return array_values(array_filter($visitors, is_string(...)));
    }
}
