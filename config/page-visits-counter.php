<?php

return [

    /*
     * The class name of the page visit Eloquent model to be used.
     */
    'page_visit_model' => Cyrildewit\PageVisitsCounter\Models\PageVisit::class,

    /*
     * The table name of the page visits database table.
     * It is used by creating the migrations files and default Eloquent model.
     */
    'page_visits_table_name' => 'page-visits',

    /*
     * The key thas used to store page visits into the session. This is used by
     * the SessionHistory class that handles the visits with expiry dates.
     */
    'page_visits_history_session_key' => 'page-visits-counter.history',

    /*
     * Number format output settings.
     */
    'output-settings' => [

        /*
         * The configured option values will be used
         * inside the official php `number_format()` function.
         *
         * Example: 120000    ==> 120.000
         * Example: 500206000 ==> 502.006.000
         */
        'format-options' => [

            'decimals' => 0,
            'dec_point' => ',',
            'thousands_sep' => '.',

        ],

    ],

];
