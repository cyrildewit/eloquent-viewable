<?php

use CyrildeWit\EloquentViewable\Doctor\Checks\IndexAdviceCheck;
use CyrildeWit\EloquentViewable\Doctor\Checks\ScheduleCheck;
use CyrildeWit\EloquentViewable\Doctor\Checks\SchemaCheck;
use CyrildeWit\EloquentViewable\Doctor\Checks\SharedCacheCheck;
use CyrildeWit\EloquentViewable\Models\View;
use CyrildeWit\EloquentViewable\Recording\Guards\EnforceCooldown;
use CyrildeWit\EloquentViewable\Recording\Guards\IgnoreBursts;
use CyrildeWit\EloquentViewable\Recording\Guards\IgnoreCrawlers;
use CyrildeWit\EloquentViewable\Recording\Guards\IgnoreDoNotTrack;
use CyrildeWit\EloquentViewable\Recording\Guards\IgnoreGlobalPrivacyControl;
use CyrildeWit\EloquentViewable\Recording\Guards\IgnoreHeadRequests;
use CyrildeWit\EloquentViewable\Recording\Guards\IgnoreIpAddresses;
use CyrildeWit\EloquentViewable\Recording\Guards\IgnoreMissingUserAgent;
use CyrildeWit\EloquentViewable\Recording\Guards\IgnorePrefetch;
use CyrildeWit\EloquentViewable\Recording\Guards\ThrottleVisitors;

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
         * queueing is enabled. The `redis` driver appends the view to a
         * Redis stream and lands it in the views table in batches when
         * `views:flush` runs. The `null` driver discards every view, for
         * environments that should not record anything. The `array` driver
         * keeps views in memory for the process. Register your own driver
         * with `StoreManager::extend()`.
         *
         * Counts always read from the views table, whichever driver is set.
         * Under the `redis` driver a view counts once it has been flushed.
         */
        'store' => [

            'driver' => 'database',

            'redis' => [

                /*
                 * The Redis connection from `database.redis` that holds the
                 * stream. When `null`, the default connection is used.
                 */
                'connection' => null,

                /*
                 * The key of the stream and the name of the consumer group
                 * the flusher reads it through.
                 */
                'stream' => 'eloquent-viewable:views',

                'group' => 'eloquent-viewable',

                /*
                 * The store driver flushed views land in. Any driver but
                 * `redis` itself.
                 */
                'landing' => 'database',

            ],

        ],

        /*
         * The guards every recorded view passes, in order. The first one
         * that refuses drops the view. Listing a guard is the only switch:
         * add it to turn its check on, remove it to turn the check off, or
         * list a class of your own that implements
         * `Recording\Contracts\RecordingGuard`.
         *
         * Out of the box crawlers, requests without a user agent, `HEAD`
         * requests, `ignored_ip_addresses`, pages the browser only prefetches
         * and bursts of views are dropped, and `EnforceCooldown` is listed
         * because `cooldown()` does nothing without it. Uncomment the others
         * to turn them on:
         *
         *   IgnoreCrawlers              drops views whose user agent the
         *                               bound `CrawlerDetector` flags
         *   IgnoreMissingUserAgent      drops requests without a user agent,
         *                               such as scripts and health checks
         *   IgnoreIpAddresses           drops views from `ignored_ip_addresses`
         *   IgnoreHeadRequests          drops `HEAD` requests, such as uptime
         *                               monitors and link checkers
         *   IgnorePrefetch              drops prefetched and prerendered pages
         *   IgnoreBursts                drops a visitor that opens many
         *                               different models within seconds, see
         *                               `bursts`
         *   ThrottleVisitors            caps the views of one visitor per
         *                               minute, see `throttle`
         *   IgnoreDoNotTrack            honours the `DNT: 1` header
         *   IgnoreGlobalPrivacyControl  honours the `Sec-GPC: 1` header
         *
         * The order only decides which guard is asked first. A cooldown starts
         * once every guard has allowed the view, wherever it is listed.
         */
        'guards' => [
            IgnoreCrawlers::class,
            IgnoreMissingUserAgent::class,
            IgnoreIpAddresses::class,
            IgnoreHeadRequests::class,
            IgnorePrefetch::class,
            IgnoreBursts::class,
            EnforceCooldown::class,
            // ThrottleVisitors::class,
            // IgnoreDoNotTrack::class,
            // IgnoreGlobalPrivacyControl::class,
        ],

        /*
         * Views from these IP addresses are dropped by `IgnoreIpAddresses`.
         * A CIDR range such as `10.0.0.0/8` or `2001:db8::/32` drops every
         * address in it.
         */
        'ignored_ip_addresses' => [

            // '127.0.0.1',

        ],

        'throttle' => [

            /*
             * How many views one visitor may record per minute, across every
             * viewable, once `ThrottleVisitors` is listed. Views over the
             * limit are dropped.
             */
            'max_per_minute' => 60,

            /*
             * The cache store the counts are kept in. Every server that
             * records views must share it. When `null`, the application's
             * default cache store is used.
             */
            'store' => null,

            /*
             * The cache key prefix the counts are kept under.
             */
            'key' => 'cyrildewit.eloquent-viewable.throttle',

        ],

        'bursts' => [

            /*
             * A visitor that opens more than `max` different viewables within
             * `seconds` is refused by `IgnoreBursts`, and so is every view of
             * theirs for the next `block_for` seconds. People do not read
             * that fast; scrapers walking through a site do.
             */
            'max' => 8,

            'seconds' => 2,

            'block_for' => 120,

            /*
             * What a burst is counted per. `visitor` is the stored visitor id.
             * `network` is a hash of the network and the user agent, the same
             * one the `fingerprint` identity uses, so a bot that drops its
             * cookie on every request is still caught. The hash lives only in
             * the cache, for a few seconds, and is never stored with a view.
             */
            'by' => ['visitor', 'network'],

            /*
             * The cache store the counts are kept in. Every server that
             * records views must share it. When `null`, the application's
             * default cache store is used.
             */
            'store' => null,

            /*
             * The cache key prefix the counts are kept under.
             */
            'key' => 'cyrildewit.eloquent-viewable.bursts',

        ],

        /*
         * Whether the signed-in model is stored as the viewer of each view,
         * in the `viewer_type` and `viewer_id` columns. Off by default,
         * because it ties a view to an identity. `guard` names the auth
         * guard the model is read from; `null` is the application's default
         * guard. `views($post)->viewedBy($user)` sets the viewer explicitly
         * and does not need this switch.
         */
        'viewer' => [

            'enabled' => false,

            'guard' => null,

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

        /*
         * A route the browser posts to after the page has loaded, for pages
         * served from a full-page cache or a CDN that never reach your
         * controller. The `@viewsBeacon($post)` Blade directive prints the
         * script that calls it. Off by default, so no route is registered
         * until you turn it on.
         */
        'beacon' => [

            'enabled' => false,

            /*
             * The path the beacon route is registered under. Neutral on
             * purpose: privacy filter lists block paths with words such as
             * `beacon`, `track` or `analytics`, which would silently drop the
             * views of every visitor who runs one. Change it if a list ever
             * starts blocking it.
             */
            'prefix' => '_ev',

            /*
             * The middleware the beacon route runs. The `web` group gives the
             * request the session and cookies that cooldowns, the visitor
             * cookie and the signed-in viewer rely on, and its CSRF check
             * accepts the beacon because the browser marks it as sent from
             * your own origin. A post from another site is refused.
             */
            'middleware' => ['web'],

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
         * Where counts come from. `count()`, `countByInterval()`, `top()`
         * and the other counts all read through this source, and so do the
         * scopes when the source can be queried in SQL. The `database`
         * driver reads the views table. The `rollup` driver reads recent
         * views from the views table and older history from the rollups in
         * `retention.rollups`. Register your own driver with
         * `SourceManager::extend()`.
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
         * Counter columns on your own tables, which `views:recount` and
         * `views:maintain` write the count into, so a listing can order and
         * filter by a plain column. List a column by name for its all-time
         * count, or map it to the `unique`, `period` and `collection` of the
         * count it holds. For example:
         *
         *   Post::class => [
         *       'views_count',
         *       'unique_views_count' => ['unique' => true],
         *       'views_last_week' => ['period' => '7d'],
         *   ],
         */
        'counters' => [],

        /*
         * Counting views by interval fills every bucket between the period
         * start and end, so a wide period with a fine granularity produces a
         * large series. Calls that would produce more buckets than this
         * maximum throw instead of running. One year of hourly buckets is
         * 8,760.
         */
        'max_intervals' => 10_000,

        /*
         * `alsoViewed()` ranks what the visitors of a model also viewed.
         *
         * A model seen by fewer than `minimum_visitors` of them is left out,
         * so the ranking never reveals what one or two people looked at.
         *
         * Only the `max_visitors` most recent visitors of the model are read,
         * because the query reads every view of every visitor it pairs, and a
         * popular model has many. `null` reads them all.
         */
        'also_viewed' => [

            'minimum_visitors' => 3,

            'max_visitors' => 1_000,

        ],

        /*
         * `trending()` and `orderByTrending()` weigh each view by its age,
         * so recent views count more. `curve` is how a view loses weight:
         * `null` halves it every `half_life`, or name a class implementing
         * `Querying\Ranking\DecayCurve`, resolved from the container.
         *
         * Views are weighed per `step`. `auto` weighs per hour, or per day
         * when hours would exceed `max_steps`.
         */
        'trending' => [

            'curve' => null,

            'half_life' => '1d',

            'step' => 'auto',

            'max_steps' => 500,

        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Retention
    |--------------------------------------------------------------------------
    |
    | How long views are kept as they were recorded. Nothing is set out of the
    | box, so nothing is anonymised or deleted until you say so. Durations use
    | the period shorthand: `30d`, `12w`, `6m`, `2y`. The `views:anonymise`,
    | `views:prune` and `views:maintain` commands apply them, and need the
    | migration published with `--tag=eloquent-viewable-retention`.
    |
    */
    'retention' => [

        'anonymise' => [

            /*
             * Views older than this lose what ties them to a person. The
             * `visitor` column is re-hashed under a salt per day that is
             * destroyed afterwards, so unique counts stay exact within a day
             * but no longer link a visitor across days. `viewer` and
             * `context` become null. Must not be longer than `prune.after`.
             */
            'after' => null,

            /*
             * Which of `visitor`, `viewer` and `context` are anonymised.
             */
            'columns' => ['visitor', 'viewer', 'context'],

        ],

        'prune' => [

            /*
             * Views older than this are deleted.
             */
            'after' => null,

        ],

        /*
         * How many views one statement anonymises or deletes.
         */
        'chunk' => 5_000,

        /*
         * Rollups keep the counts of old views per bucket of time, so history
         * outlives the views it was counted from. `views:rollup` folds them
         * from the views table, and setting `querying.source.driver` to
         * `rollup` reads them. They need the migration published with
         * `--tag=eloquent-viewable-rollups`.
         */
        'rollups' => [

            'table' => 'view_rollups',

            /*
             * The clock buckets align to. When `null`, the application's
             * timezone is used.
             */
            'timezone' => null,

            /*
             * How long a closed bucket waits for views that land late, from
             * a queue or the Redis buffer, before it is folded.
             */
            'settle' => '1h',

            /*
             * The tiers to keep, `hour`, `day`, `month` or `year`, each with
             * how long it is kept, or `null` for forever. A coarser tier must
             * be kept at least as long as a finer one. For example:
             * `['day' => '2y', 'month' => null]`.
             */
            'tiers' => [],

            /*
             * What a bucket is counted per. Views add up across groupings,
             * unique visitors do not, so each count needs a grouping of its
             * own:
             *
             *   viewable             a model across its collections
             *   viewable_collection  a model within one collection
             *   type                 every model of a type
             *   type_collection      every model of a type within one
             *                        collection
             *
             * A count that needs a grouping that is not kept reads the views
             * table.
             */
            'groupings' => ['viewable', 'viewable_collection', 'type'],

            /*
             * Rollups of your own, each a class that extends
             * `Querying\Rollups\Rollup`: a filter, its tiers and groupings,
             * and at most one dimension. Read them with
             * `views($post)->rollup('name')`.
             */
            'custom' => [

                // App\Rollups\NewsletterViews::class,

            ],

            /*
             * History beyond the views table has the resolution of its
             * tier: a bucket counts when its start lies inside the period,
             * and unique visitors are summed across buckets. When `true`,
             * a count that cannot be answered exactly throws instead.
             */
            'strict' => false,

        ],

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

        /*
         * What identifies a visitor in the `visitor` column, which `unique()`
         * counts and a cooldown is keyed on. One of:
         *
         *   cookie   the random id from the cookie below, so a visitor is a
         *            browser
         *   viewer   an HMAC of the signed-in model's type and key with
         *            `app.key`, so a visitor is an account on every device and
         *            on an API without a cookie; guests get the cookie id
         *   fingerprint
         *            a hash of the truncated IP address and the user agent
         *            under a random salt that is replaced at midnight, so no
         *            cookie is set and a guest is a new visitor every day;
         *            signed-in models get the `viewer` id
         *
         * The signed-in model comes from `recording.viewer` or `viewedBy()`.
         */
        'identity' => 'cookie',

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

        'fingerprint' => [

            /*
             * The cache store the daily salt is kept in. Every server that
             * records views must share it, or each hashes under its own salt
             * and one visitor counts once per server. When `null`, the
             * application's default cache store is used.
             */
            'store' => null,

            /*
             * The cache key prefix the daily salt is kept under.
             */
            'key' => 'cyrildewit.eloquent-viewable.fingerprint',

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

    /*
    |--------------------------------------------------------------------------
    | Doctor
    |--------------------------------------------------------------------------
    |
    | `views:doctor` runs these checks and says what to fix.
    |
    */
    'doctor' => [

        /*
         * The checks to run, in order. Remove one to skip it, or list a
         * class of your own that implements `Doctor\Contracts\Check`.
         */
        'checks' => [
            SchemaCheck::class,
            IndexAdviceCheck::class,
            SharedCacheCheck::class,
            ScheduleCheck::class,
        ],

    ],

];
