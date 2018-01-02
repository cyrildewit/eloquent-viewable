<?php

use Carbon\Carbon;

return [

    /*
     * Our "HasPageViewCounter" trait needs to know which Eloquent model should
     * be used to retrieve your page views.
     *
     * The model you want to use as a PageView model needs to implement the
     * `CyrildeWit\PageViewCounter\Contracts\PageView` contract.
     */
    'page_view_model' => CyrildeWit\PageViewCounter\Models\PageView::class,

    /*
     * Our "HasPageViewCounter" trait needs to know which table should be used
     * to retrieve your page views.
     *
     * It is used by creating the migrations files and default Eloquent model.
     */
    'page_views_table_name' => 'page-views',

    /*
     * The below key is used by the PageViewHistory class that handles the page
     * views with expiry dates. Make sure that it's unique.
     */
    'page_view_history_session_key' => 'page-view-counter.session.history',

    /*
     * Register here your custom date transformers. When the package get one of
     * the below keys, it will use the value instead.
     *
     * Keep it empty, if you don't want any date transformers!
     *
     * Example:
     * - $article->getPageViewsFrom('past24hours'); // Get the total page views of the past 24 hours
     * - $article->getPageViewsFrom('past14days'); // Get the total page views of the past 14 days
     */
    'date-transformers' => [
        // 'past24hours' => Carbon::now()->subDays(1),
        // 'past7days' => Carbon::now()->subWeeks(1),
        // 'past14days' => Carbon::now()->subWeeks(2),
    ],

];
