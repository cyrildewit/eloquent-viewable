<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Querying\Contracts;

use CyrildeWit\EloquentViewable\Contracts\Viewable;
use CyrildeWit\EloquentViewable\Support\ViewsQuery;

/**
 * Implement this on a view source that counts how many days each visitor
 * viewed a viewable on. It is kept apart from `ViewSource`, so a source of
 * your own keeps working without it.
 */
interface CountsVisitFrequency
{
    /**
     * The number of visitors keyed by the number of days they viewed on, a
     * day on the clock of the query's timezone or else the application's.
     * Views without a visitor and anonymised views are left out. Only day
     * counts with visitors are present, in any order.
     *
     * @return array<int, int>
     */
    public function visitFrequency(Viewable $viewable, ViewsQuery $query): array;
}
