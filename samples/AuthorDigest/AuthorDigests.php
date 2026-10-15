<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Samples\AuthorDigest;

use Carbon\CarbonImmutable;
use CyrildeWit\EloquentViewable\Facades\Views;
use CyrildeWit\EloquentViewable\Querying\Comparison\ViewComparison;
use CyrildeWit\EloquentViewable\Support\Period;
use Illuminate\Database\Eloquent\Collection;

class AuthorDigests
{
    private const int TopLimit = 3;

    /**
     * The digest of the week that starts on `$week`, or null when none of the
     * author's essays was viewed in it.
     */
    public function for(Author $author, CarbonImmutable $week): ?WeeklyDigest
    {
        $essays = $author->essays()->orderBy('id')->get(['id', 'author_id', 'title']);

        // Calendar weeks on the author's clock. The week a clock goes back
        // is 169 hours long, so stepping back by the width of this week, as
        // `compare()` does with a period of two dates, would start the week
        // before at 23:00 on the Sunday before it.
        $currentWeek = Period::create($week, $week->addWeek());
        $previousWeek = Period::create($week->subWeek(), $week);

        $current = $this->counts($essays, $currentWeek);
        $previous = $this->counts($essays, $previousWeek);

        $total = array_sum($current);

        if ($total === 0) {
            return null;
        }

        arsort($current);

        $top = [];

        foreach (array_slice($current, 0, self::TopLimit, preserve_keys: true) as $id => $views) {
            if ($views > 0) {
                $top[] = ['title' => $essays->findOrFail($id)->title, 'views' => $views];
            }
        }

        return new WeeklyDigest(
            week: $week,
            views: ViewComparison::between($total, array_sum($previous), $currentWeek, $previousWeek),
            top: $top,
        );
    }

    /**
     * The views of each essay in one query, zero for an essay without any.
     *
     * @param  Collection<int, Essay>  $essays
     * @return array<int|string, int>
     */
    private function counts(Collection $essays, Period $period): array
    {
        return Views::forViewables($essays)->period($period)->counts()->all();
    }
}
