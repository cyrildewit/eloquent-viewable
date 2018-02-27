<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Eloquent Models
    |--------------------------------------------------------------------------
    */

    'models' => [

        /*
         * When using the "Visitable" trait from this package, it needs to
         * know which model should be used to retrieve and store the visits.
         *
         * We have created a simple default Eloquent model that could be used
         * `CyrildeWit\EloquentVisitable\Models\Visit::class`, but if you
         * need to extend it, you can easily change the below value.
         *
         * The model you want to use as a Visit model needs to implement the
         * `CyrildeWit\EloquentVisitable\Contracts\Models\Visit`.
         */
        'visit' => CyrildeWit\EloquentVisitable\Models\Visit::class,

    ],

    /*
    |--------------------------------------------------------------------------
    | Database Tables
    |--------------------------------------------------------------------------
    */

    'table_names' => [

        /*
         * When using the "Visitable" trait from this package, it needs to
         * know which table should be used to retrieve and store the visits.
         */
        'visits' => 'visits',

    ],

    /*
    |--------------------------------------------------------------------------
    | Queueable Jobs
    |--------------------------------------------------------------------------
    */

    'jobs' => [

        /*
         * If you have a ton of visitors visiting pages where you save model
         * visits. It might be a good idea to offload it using Laravel's queue.
         * Make sure that your Laravel application is ready for queueing,
         * otherwise this won't work.
         *
         * If you want to dispatch this job to a particular queue, change the
         * value of 'onQueue' to something else. Skip this by setting the value
         * to 'null'.
         *
         * If you want to dispatch this job to a particular connection, change
         * the value of 'onConnection' to something else. Skip this by setting
         * the value to 'null'.
         */
        'store_new_visit' => [

            'enabled' => false,
            'delay_in_seconds' => 60 * 2,
            'onQueue' => null,
            'onConnection' => null,

        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Cache Configuration
    |--------------------------------------------------------------------------
    */

    'cache' => [

        /*
         * Determine if this package should cache data.
         */
        'enabled' => env('ELOQUENT_VISITABLE_CACHE_ENABLED', true),

        /*
         * Everthing will be cached under the following key.
         */
        'key' => 'cyrildewit.eloquent-visitable.cache',

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
         * you want to change that, simply edit the value of
         * 'lifetime_in_minutes' to something else. Make sure it is
         * in minutes!
         */
        'cache_visits_count' => [

            'enabled' => true,
            'lifetime_in_minutes' => 60 * 10,

        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Date Transformers
    |--------------------------------------------------------------------------
    |
    | Here you can register custom date transformers. When the package get one
    | of the below keys inserted, it will use the value instead.
    |
    | Keep it empty, if you don't want to use this feature!
    |
    */
    'date-transformers' => [
        // 'past24hours' => Carbon::now()->subDays(1),
        // 'past7days' => Carbon::now()->subWeeks(1),
        // 'past14days' => Carbon::now()->subWeeks(2),
    ],

];
