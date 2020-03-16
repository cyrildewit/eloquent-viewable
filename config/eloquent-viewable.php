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
            'connection' => env('DB_CONNECTION', 'mysql'),

        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Cache Configuration
    |--------------------------------------------------------------------------
    */
    'cache' => [

        /*
         * Everthing will be stored under the following key.
         */
        'key' => 'cyrildewit.eloquent-viewable.cache',

        /*
         * Here you may define the cache store that should be used.
         */
        'store' => env('CACHE_DRIVER', 'file'),

        /*
         * Default lifetime of cached views count in minutes.
         */
        'lifetime_in_minutes' => 60,

    ],

    /*
    |--------------------------------------------------------------------------
    | Cooldown Configuration
    |--------------------------------------------------------------------------
    */
    'cooldown' => [

        /*
         * Everthing will be stored under the following key in the session.
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
