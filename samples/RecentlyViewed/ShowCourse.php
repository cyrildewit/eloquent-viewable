<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Samples\RecentlyViewed;

use Illuminate\Http\Request;

class ShowCourse
{
    /**
     * @return array{title: string, last_opened_at: ?string}
     */
    public function __invoke(Request $request, Course $course): array
    {
        return [
            'title' => $course->title,
            'last_opened_at' => $this->lastOpenedAt($request, $course),
        ];
    }

    /**
     * The `views` middleware records once the response is built, so this is
     * the visit before the current one: "welcome back, you last opened this
     * on ...". Null for a guest or a first visit.
     */
    private function lastOpenedAt(Request $request, Course $course): ?string
    {
        $learner = $request->user();

        if (! $learner instanceof Learner) {
            return null;
        }

        return $learner->lastViewedAt($course)?->toIso8601String();
    }
}
