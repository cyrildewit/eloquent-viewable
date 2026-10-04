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
        $learner = $request->user();

        return [
            'title' => $course->title,
            // The `views` middleware records once the response is built, so
            // this is the visit before the current one: "welcome back, you
            // last opened this on ...".
            'last_opened_at' => $learner instanceof Learner
                ? $learner->lastViewedAt($course)?->toIso8601String()
                : null,
        ];
    }
}
