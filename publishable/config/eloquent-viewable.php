<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Eloquent Models
    |--------------------------------------------------------------------------
    */

    'models' => [

        /*
         * Here you can configure the default `View` model.
         */
        'view' => [

            'table_name' => 'views',
            'connection' => null,

        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Queueable Jobs
    |--------------------------------------------------------------------------
    */

    'jobs' => [

        /*
         * If you have a ton of visitors visiting pages where you save model
         * views. It might be a good idea to offload it using Laravel's queue.
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
        'store_new_view' => [

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
        'enabled' => false,

        /*
         * Everthing will be cached under the following key.
         */
        'key' => 'cyrildewit.eloquent-viewable.cache',

        /*
         * Caching views counts can speed up your request. Instead of
         * counting on each request, the package will retrieve this data
         * from the cache. If the cache is empty, it will recount and cache
         * that value.
         *
         * Enable this by setting the value of 'enabled' to 'true' (default)
         * or disable it by setting this value to 'false'.
         *
         * By default each views count will be stored for 60 minutes. If
         * you want to change that, simply edit the value of
         * 'lifetime_in_minutes' to something else. Make sure it is
         * in minutes!
         */
        'cache_views_count' => [

            'enabled' => true,
            'lifetime_in_minutes' => 60,

        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Session Configuration
    |--------------------------------------------------------------------------
    */

    'session' => [

        /*
         * Everthing will be stored under the following key.
         */
        'key' => 'cyrildewit.eloquent-viewable.session',

    ],

    /*
    |--------------------------------------------------------------------------
    | Ignore Bots
    |--------------------------------------------------------------------------
    |
    | If you want to ignore bots, you can specify that here. The default
    | service that determines if a visitor is a crawler is a package
    | by JayBizzle called CrawlerDetect.
    |
    */
    'ignore_bots' => true,

    /*
    |--------------------------------------------------------------------------
    | Do Not Track Header
    |--------------------------------------------------------------------------
    |
    | If you want to honor the DNT header, you can specify that here.
    |
    */
    'honor_dnt' => false,

    /*
    |--------------------------------------------------------------------------
    | Cookies
    |--------------------------------------------------------------------------
    |
    | This package binds visitors to views using a cookie. If you want to
    | give this cookie a custom name, you can specify that here.
    |
    */

    'visitor_cookie_key' => 'eloquent_viewable',

    /*
    |--------------------------------------------------------------------------
    | Ignore IP Addresses
    |--------------------------------------------------------------------------
    |
    | Ignore views of the following IP addresses.
    |
    */

    'ignored_ip_addresses' => [

        // '127.0.0.1',

    ],

];
