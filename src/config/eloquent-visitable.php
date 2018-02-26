<?php

use Carbon\Carbon;

return [

    'models' => [

        /*
         * When using the "Visitable" trait from this package, it needs to
         * know which model should be used to retrieve and store the visits.
         *
         * We have created a simple default Eloquent model that could be used:
         * `CyrildeWit\EloquentVisitable\Models\Visit::class`. But if you
         * need to extend it, you can easily change the below value.
         *
         * The model you want to use as a Visit model needs to implement the
         * `CyrildeWit\EloquentVisitable\Contracts\Models\Visit`
         */
        'visit' => CyrildeWit\EloquentVisitable\Models\Visit::class,

    ],

    'table_names' => [

        /*
         * When using the "Visitable" trait from this package, it needs to
         * know which table should be used to retrieve and store the visits.
         */
        'visits' => 'visits',

    ],

    'jobs' => [

        /*
         * When storing a new visit in the database, it could slow down your
         * aplication. You can turn queueing on for this job by changing the
         * value of `queue` to `true., if you want. Make sure your Laravel is
         * ready for queueing.
         */
        'store-new-visit' => [

            'queue' => false,
            'delay_in_seconds' => 30,

        ],

    ],

    'cache' => [

        /*
         * Determine if the this package should cache data.
         */
        'enabled' => env('ELOQUENT_VISITABLE_CACHE_ENABLED', true),

        /*
         * Everthing will be cached under the following key.
         */
        'key' => env('ELOQUENT_VISITABLE_CACHE_ENABLED', 'cyrildewit.eloquent-visitable.cache'),


        'events' => [

            /*
             * Caching visits counts can speed up your request. Instead of
             * counting on each request, the package will retrieve this data
             * from the cache. If the cache is empty, it will recount and cache
             * that value.
             *
             * Enable this by setting the value of 'enabled' to 'true' (default)
             * or disable it by setting this value to 'false'.
             *
             * By default each visits count will be stored for 10 minutes. If
             * you want to change this, simply change the value of
             * 'default_lifetime_in_minutes' to something else. Make sure it is
             * in minutes!
             */
            'cache_visits_count' => [

                'enabled' => true,
                'default_lifetime_in_minutes' => 60 * 10,

            ],

        ],

    ],

    /*
     * Register here your custom date transformers. When the 7package get one of
     * the below keys, it will use the value instead.
     *
     * Keep it empty, if you don't want any date transformers!
     *
     * More information at:
     * github.com/cyrildewit/eloquent-visitable/README.md#defining-date-transformers
     */
    'date-transformers' => [
        // 'past24hours' => Carbon::now()->subDays(1),
        // 'past7days' => Carbon::now()->subWeeks(1),
        // 'past14days' => Carbon::now()->subWeeks(2),
    ],

];
