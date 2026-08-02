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

            /*
             * The database connection used to store views. When `null`, the
             * application's default database connection is used.
             */
            'connection' => null,

        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Cache Configuration
    |--------------------------------------------------------------------------
    */
    'cache' => [

        /*
         * Everything will be stored under the following key.
         */
        'key' => 'cyrildewit.eloquent-viewable.cache',

        /*
         * Here you may define the cache store that should be used. When
         * `null`, the application's default cache store is used.
         */
        'store' => null,

    ],

    /*
    |--------------------------------------------------------------------------
    | Queue Configuration
    |--------------------------------------------------------------------------
    |
    | When enabled, views are dispatched to the queue and stored by a worker
    | instead of during the request. This defers the database write to speed
    | up response times. You may also queue individual views on the fly using
    | the `queue()` method: `views($post)->queue()->record()`.
    |
    */
    'queue' => [

        /*
         * Whether views should be queued before they are stored by default.
         */
        'enabled' => false,

        /*
         * The queue connection used to store views. When `null`, the
         * application's default queue connection is used.
         */
        'connection' => null,

        /*
         * The queue used to store views. When `null`, the default queue
         * of the connection is used.
         */
        'queue' => null,

    ],

    /*
    |--------------------------------------------------------------------------
    | Cooldown Configuration
    |--------------------------------------------------------------------------
    */
    'cooldown' => [

        /*
         * Everything will be stored under the following key in the session.
         */
        'key' => 'cyrildewit.eloquent-viewable.cooldowns',

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
    | If you want to honor the DNT header, you can specify that here. We won't
    | record views from visitors with the Do Not Track header.
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
