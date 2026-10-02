<?php

use CyrildeWit\EloquentViewable\Models\View;
use CyrildeWit\EloquentViewable\Recording\Guards\EnforceCooldown;
use CyrildeWit\EloquentViewable\Recording\Guards\IgnoreCrawlers;
use CyrildeWit\EloquentViewable\Recording\Guards\IgnoreDoNotTrack;
use CyrildeWit\EloquentViewable\Recording\Guards\IgnoreGlobalPrivacyControl;
use CyrildeWit\EloquentViewable\Recording\Guards\IgnoreIpAddresses;

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

            /*
             * The model used for views. A custom model must extend the
             * shipped one.
             */
            'class' => View::class,

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
    | Recording
    |--------------------------------------------------------------------------
    |
    | Everything that happens when `views($post)->record()` is called: the
    | guards the attempt passes, where the view goes, and whether the write
    | is queued.
    |
    */
    'recording' => [

        /*
         * Where a recorded view goes. The `database` driver writes a row to
         * the views table during the request, or from a queue worker when
         * queueing is enabled. The `null` driver discards every view, for
         * environments that should not record anything. The `array` driver
         * keeps views in memory for the process. Register your own driver
         * with `StoreManager::extend()`.
         *
         * Counts always read from the views table, whichever driver is set.
         */
        'store' => [

            'driver' => 'database',

        ],

        /*
         * The guards every recorded view passes, in order. The first one
         * that refuses drops the view. Listing a guard is the only switch:
         * add it to turn its check on, remove it to turn the check off, or
         * list a class of your own that implements
         * `Recording\Contracts\RecordingGuard`.
         *
         * Out of the box crawlers and `ignored_ip_addresses` are dropped, as
         * in v8, and `EnforceCooldown` is listed because `cooldown()` does
         * nothing without it. Uncomment the privacy guards to honour those
         * headers, or remove a guard to turn its check off:
         *
         *   IgnoreCrawlers              drops views whose user agent the
         *                               bound `CrawlerDetector` flags
         *   IgnoreIpAddresses           drops views from `ignored_ip_addresses`
         *   IgnoreDoNotTrack            honours the `DNT: 1` header
         *   IgnoreGlobalPrivacyControl  honours the `Sec-GPC: 1` header
         *
         * The order only decides which guard is asked first. A cooldown starts
         * once every guard has allowed the view, wherever it is listed.
         */
        'guards' => [
            IgnoreCrawlers::class,
            IgnoreIpAddresses::class,
            EnforceCooldown::class,
            // IgnoreDoNotTrack::class,
            // IgnoreGlobalPrivacyControl::class,
        ],

        /*
         * Views from these IP addresses are dropped by `IgnoreIpAddresses`.
         */
        'ignored_ip_addresses' => [

            // '127.0.0.1',

        ],

        /*
         * When enabled, views are dispatched to the queue and stored by a
         * worker instead of during the request. This defers the database
         * write to speed up response times. You may also queue individual
         * views on the fly using the `queue()` method:
         * `views($post)->queue()->record()`.
         */
        'queue' => [

            /*
             * Whether views should be queued before they are stored by
             * default.
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

    ],

    /*
    |--------------------------------------------------------------------------
    | Querying
    |--------------------------------------------------------------------------
    |
    | Everything that happens when a count is read: where the numbers come
    | from, how `remember()` caches them, and how large an interval series
    | may grow.
    |
    */
    'querying' => [

        /*
         * Where counts come from. `count()`, `countByInterval()` and the
         * `withViewsCount()` and `orderByViews()` scopes all read through
         * this source. The `database` driver reads the views table. Register
         * your own driver with `SourceManager::extend()`, for example to read
         * a rollup table instead.
         */
        'source' => [

            'driver' => 'database',

        ],

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
         * Counting views by interval fills every bucket between the period
         * start and end, so a wide period with a fine granularity produces a
         * large series. Calls that would produce more buckets than this
         * maximum throw instead of running. One year of hourly buckets is
         * 8,760.
         */
        'max_intervals' => 10_000,

    ],

    /*
    |--------------------------------------------------------------------------
    | Visitor
    |--------------------------------------------------------------------------
    |
    | How the package tells one visitor from the next. Visitors are bound to
    | their views through a cookie holding a random identifier.
    |
    */
    'visitor' => [

        'cookie' => [

            /*
             * The name of the cookie.
             */
            'name' => 'eloquent_viewable',

            /*
             * How long the cookie lives, in minutes. Five years by default.
             */
            'lifetime' => 2_628_000,

        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Cooldown
    |--------------------------------------------------------------------------
    */
    'cooldown' => [

        /*
         * Where running cooldowns are kept: `session`, as in v8, or `cache`,
         * which also works on routes without a session. Register your own
         * driver with `CooldownManager::extend()`.
         */
        'store' => 'session',

        /*
         * The session key, or the cache key prefix, cooldowns are kept under.
         */
        'key' => 'cyrildewit.eloquent-viewable.cooldowns',

        'cache' => [

            /*
             * When `null`, the application's default cache store is used.
             */
            'store' => null,

        ],

    ],

];
