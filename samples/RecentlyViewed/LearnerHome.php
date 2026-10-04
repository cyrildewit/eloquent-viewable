<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Samples\RecentlyViewed;

use Illuminate\Database\Eloquent\Collection;

class LearnerHome
{
    /**
     * The courses the learner opened, the most recently opened first and
     * each course once, however often it was opened.
     *
     * @return Collection<int, Course>
     */
    public function continueWhereYouLeftOff(Learner $learner, int $limit = 5): Collection
    {
        // `viewed()` is every view of the learner, newest first. One row per
        // course is wanted here, ordered by its latest view.
        $lastOpened = $learner->viewed()
            ->reorder()
            ->where('viewable_type', new Course()->getMorphClass())
            ->selectRaw('viewable_id, max(viewed_at) as last_opened_at')
            ->groupBy('viewable_id')
            ->orderByDesc('last_opened_at')
            ->orderByDesc('viewable_id')
            ->limit($limit)
            ->pluck('last_opened_at', 'viewable_id');

        $position = array_flip($lastOpened->keys()->all());

        // A course deleted since is not found and drops out of the list.
        return Course::query()
            ->findMany($lastOpened->keys()->all())
            ->sortBy(fn (Course $course): int => $position[$course->id])
            ->values();
    }

    /**
     * The newest courses the learner has never opened.
     *
     * @return Collection<int, Course>
     */
    public function notOpenedYet(Learner $learner, int $limit = 5): Collection
    {
        return Course::query()
            ->whereNotViewedBy($learner)
            ->latest('id')
            ->limit($limit)
            ->get();
    }
}
