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
