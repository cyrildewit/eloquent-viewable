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

    /*
     * By default everything will be cached under the following key. You can
     * change it if you want.
     */
    'cache-key' => 'cyrildewit.eloquent-visitable.cache',

    /*
     * By default all retrieved visits will be cached for 30 minutes.
     */
    'cache_expiration_time' => 30,

    /*
     * Register here your custom date transformers. When the package get one of
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
