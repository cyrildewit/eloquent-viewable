<?php

return [

    'models' => [

        /*
         * This model is used by default to store
         * the page visits into the database.
         */
         'page-visit' => Cyrildewit\PageVisitsCounter\Models\PageVisit::class,

    ],

    'table_names' => [

        /*
         * This table is used by creating the migrations
         * files and default model.
         */
        'page-visits' => 'page-visits',

    ],

    'sessions' => [

        'primary-session-key' => 'page-visits-counter.history',

    ],

    'output-settings' => [

        /*
         * Set true for formatted number output.
         */
        'formatted-output-enabled' => true,

        /*
         * The following optiosn will be used inside the
         * `number_format`function.
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
