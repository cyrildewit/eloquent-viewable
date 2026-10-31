<?php

use CyrildeWit\EloquentViewable\Doctor\Checks\ConfigurationCheck;
use CyrildeWit\EloquentViewable\Doctor\Checks\CrawlerShareCheck;
use CyrildeWit\EloquentViewable\Doctor\Checks\DimensionsCheck;
use CyrildeWit\EloquentViewable\Doctor\Checks\IndexAdviceCheck;
use CyrildeWit\EloquentViewable\Doctor\Checks\RedisStreamCheck;
use CyrildeWit\EloquentViewable\Doctor\Checks\ScheduleCheck;
use CyrildeWit\EloquentViewable\Doctor\Checks\SchemaCheck;
use CyrildeWit\EloquentViewable\Doctor\Checks\SharedCacheCheck;
use CyrildeWit\EloquentViewable\Doctor\Checks\TrustedProxiesCheck;
use CyrildeWit\EloquentViewable\Models\View;
use CyrildeWit\EloquentViewable\Recording\Guards\EnforceCooldown;
use CyrildeWit\EloquentViewable\Recording\Guards\IgnoreBursts;
use CyrildeWit\EloquentViewable\Recording\Guards\IgnoreCrawlers;
use CyrildeWit\EloquentViewable\Recording\Guards\IgnoreDoNotTrack;
use CyrildeWit\EloquentViewable\Recording\Guards\IgnoreGlobalPrivacyControl;
use CyrildeWit\EloquentViewable\Recording\Guards\IgnoreHeadRequests;
use CyrildeWit\EloquentViewable\Recording\Guards\IgnoreIpAddresses;
use CyrildeWit\EloquentViewable\Recording\Guards\IgnoreMissingUserAgent;
use CyrildeWit\EloquentViewable\Recording\Guards\IgnoreOptedOutViewers;
use CyrildeWit\EloquentViewable\Recording\Guards\IgnorePrefetch;
use CyrildeWit\EloquentViewable\Recording\Guards\ThrottleVisitors;

return [

    /*
    |--------------------------------------------------------------------------
    | Eloquent Models
    |--------------------------------------------------------------------------
    */
    'models' => [

        'view' => [

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
         * Where a recorded view goes:
         *
         * - `database` writes it to the views table, during the request or
         *   from a queue worker when queueing is enabled.
         * - `redis` buffers it in a Redis stream until `views:flush` moves it
         *   to the views table in batches.
         * - `array` keeps it in memory for the process.
         * - `null` discards it.
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
         * The guards every recorded view passes. The first one that refuses
         * drops the view. Add or remove a guard to turn its check on or off,
         * or list your own class implementing `Recording\Contracts\RecordingGuard`.
         *
         *   IgnoreCrawlers              drops views whose user agent the
         *                               bound `CrawlerDetector` flags
         *   IgnoreMissingUserAgent      drops requests without a user agent,
         *                               such as scripts and health checks
         *   IgnoreIpAddresses           drops views from `ignored_ip_addresses`
         *   IgnoreHeadRequests          drops `HEAD` requests, such as uptime
         *                               monitors and link checkers
         *   IgnorePrefetch              drops prefetched and prerendered pages
         *   IgnoreOptedOutViewers       drops the views of a viewer whose
         *                               model implements `ViewerCanOptOut`
         *                               and returns false from
         *                               `tracksViews()`
         *   IgnoreBursts                drops a visitor that opens many
         *                               different models within seconds, see
         *                               `bursts`
         *   EnforceCooldown             drops a repeat view while a
         *                               `cooldown()` runs
         *   ThrottleVisitors            caps the views of one visitor per
         *                               minute, see `throttle`
         *   IgnoreDoNotTrack            honours the `DNT: 1` header
         *   IgnoreGlobalPrivacyControl  honours the `Sec-GPC: 1` header
         */
        'guards' => [
            IgnoreCrawlers::class,
            IgnoreMissingUserAgent::class,
            IgnoreIpAddresses::class,
            IgnoreHeadRequests::class,
            IgnorePrefetch::class,
            IgnoreOptedOutViewers::class,
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
             * The most views one visitor may record per minute, counted
             * across all viewables. Views over the limit are dropped.
             *
             * Only applies when `ThrottleVisitors` is enabled in `guards`.
             */
            'max_per_minute' => 60,

            /*
             * The cache store that holds the counts. When `null`, the
             * application's default cache store is used.
             *
             * Every server that records views must use the same store.
             * Otherwise each server keeps its own counts, and the limit
             * applies per server instead of in total.
             */
            'store' => null,

            /*
             * The cache key prefix the counts are kept under.
             */
            'key' => 'cyrildewit.eloquent-viewable.throttle',

        ],

        'bursts' => [

            /*
             * When a visitor opens more than `max` different viewables within
             * `seconds`, `IgnoreBursts` drops that view and blocks all their
             * views for the next `block_for` seconds.
             *
             * People do not read that fast, but scrapers that walk through a
             * site do.
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
             * The cache store that holds the counts. When `null`, the
             * application's default cache store is used.
             *
             * Every server that records views must use the same store.
             * Otherwise each server keeps its own counts, and the limit
             * applies per server instead of in total.
             */
            'store' => null,

            /*
             * The cache key prefix the counts are kept under.
             */
            'key' => 'cyrildewit.eloquent-viewable.bursts',

        ],

        /*
         * When enabled, the signed-in model is stored as the viewer of each
         * view, in the `viewer_type` and `viewer_id` columns. It is off by
         * default because it links every view to a person.
         *
         * `guard` is the auth guard the signed-in model is read from. When
         * `null`, the application's default guard is used.
         *
         * `views($post)->viewedBy($user)` sets the viewer explicitly and
         * works without this setting.
         */
        'viewer' => [

            'enabled' => false,

            'guard' => null,

        ],

        /*
         * When enabled, views are stored by a queue worker instead of during
         * the request, so the database write does not slow down the response.
         *
         * `views($post)->queue()->record()` queues a single view, even when
         * this is disabled.
         */
        'queue' => [

            /*
             * Whether a view is queued when the call does not say.
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
         * When enabled, a route is registered that the browser posts to after
         * the page has loaded. Use it to record views of pages that are served
         * from a full-page cache or a CDN, and so never reach your controller.
         *
         * The `@viewsBeacon($post)` Blade directive prints the script that
         * calls this route.
         */
        'beacon' => [

            'enabled' => false,

            /*
             * The path prefix of the beacon route.
             *
             * Keep it neutral. Privacy filter lists block paths that contain
             * words such as `beacon`, `track` or `analytics`, so visitors who
             * use such a list would never record a view. Change it if a list
             * starts to block it.
             */
            'prefix' => '_ev',

            /*
             * The middleware the beacon route runs.
             *
             * Keep `web`, or a group of your own that starts the session and
             * checks the CSRF token. Cooldowns, the visitor id and the
             * signed-in viewer need the session and cookies. The CSRF check
             * accepts the beacon because the browser marks it as sent from
             * your own origin, and refuses a post from another site.
             */
            'middleware' => ['web'],

        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Dimensions
    |--------------------------------------------------------------------------
    |
    | Count views by where they came from and who saw them. Each dimension
    | turns the request into one short value when a view is recorded, kept in
    | a column of its own, and `views($post)->countBy('source')` counts the
    | views per value. None is on until you list it.
    |
    */
    'dimensions' => [

        /*
         * The dimensions to record. Uncomment a line to turn one on.
         *
         * A dimension is kept in a column named after its key. After you add
         * one, run `php artisan views:dimensions` and migrate.
         *
         * An entry is a class, or an array of the class and its options, such
         * as `[Country::class, 'resolver' => HeaderCountry::class]`. Every
         * dimension accepts these options:
         *
         *   personal   when true, anonymising clears the value
         *   maxValues  how many top values a rollup keeps per bucket, or
         *              `null` to keep every value
         *   json       a path into `context`, such as `context->plan`, to
         *              keep the value there instead of in a column
         */
        'definitions' => [

            // Google, Hacker News, the utm_source, Direct, or the referring host.
            // 'source' => CyrildeWit\EloquentViewable\Dimensions\Source::class,

            // organic, social, email, referral, cpc or direct.
            // 'medium' => CyrildeWit\EloquentViewable\Dimensions\Medium::class,

            // The lowercased utm_campaign. Personal: anonymising clears it.
            // 'campaign' => CyrildeWit\EloquentViewable\Dimensions\Campaign::class,

            // mobile, tablet, desktop, or bot when IgnoreCrawlers is off.
            // 'device' => CyrildeWit\EloquentViewable\Dimensions\Device::class,

            // The ISO country code, from the CF-IPCountry header by default.
            // 'country' => CyrildeWit\EloquentViewable\Dimensions\Country::class,

            // The referring host alone, such as news.ycombinator.com.
            // 'referrer_host' => CyrildeWit\EloquentViewable\Dimensions\ReferrerHost::class,

        ],

        /*
         * The hosts of the application itself, on top of the one in
         * `app.url`. A visitor arriving from one of them came from another
         * page of the site, so it is not counted as a referrer.
         */
        'internal_hosts' => [],

        /*
         * Hosts the `Source` and `Medium` dimensions should name, on top of
         * the list the package ships, each a source name and a medium.
         */
        'sources' => [

            // 'news.example.com' => ['Example News', 'referral'],

        ],

        /*
         * `utm_source` and `ref` values to read as a source name, such as
         * `'nl' => 'Newsletter'`.
         */
        'source_aliases' => [],

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
         * Where counts come from:
         *
         * - `database` reads the views table.
         * - `rollup` reads recent views from the views table and older
         *   history from the rollups in `retention.rollups`.
         *
         * Every count, such as `count()`, `countByInterval()` and `top()`,
         * reads from this source. The scopes do too, when the source can be
         * queried in SQL.
         */
        'source' => [

            'driver' => 'database',

        ],

        'cache' => [

            /*
             * The cache key prefix every cached count is kept under.
             */
            'key' => 'cyrildewit.eloquent-viewable.cache',

            /*
             * The cache store cached counts are kept in. When `null`, the
             * application's default cache store is used.
             */
            'store' => null,

        ],

        /*
         * Counter columns on your own tables. `views:recount` and
         * `views:maintain` write the count into them, so a listing can order
         * and filter by a plain column.
         *
         * List a column by name to hold the all-time count, or map it to
         * options that narrow the count:
         *
         *   unique      count unique visitors only
         *   period      count only the views within this period, such as `7d`
         *   collection  count only the views in this collection
         *   dimensions  count only the views with these dimension values
         *   hot         hold the score `orderByHot()` sorts by, not a count
         *
         * `hot` takes `true` for the defaults, the name of the timestamp
         * column the model was made at, or `from` and `every` options. `from`
         * defaults to `created_at`. `every` defaults to `12h`: a model that
         * much newer needs a tenth of the views to score the same.
         *
         * For example:
         *
         *   Post::class => [
         *       'views_count',
         *       'unique_views_count' => ['unique' => true],
         *       'views_last_week' => ['period' => '7d'],
         *       'hot_score' => ['hot' => ['from' => 'published_at', 'every' => '12h']],
         *       'google_views' => ['dimensions' => ['source' => 'Google']],
         *   ],
         */
        'counters' => [],

        /*
         * The most buckets one `countByInterval()` call may return. A call
         * that would return more throws instead of running.
         *
         * Every bucket between the start and end of the period is filled,
         * so a long period at a fine granularity gets large fast. One year of
         * hourly buckets is 8,760.
         */
        'max_intervals' => 10_000,

        /*
         * `alsoViewed()` ranks what the visitors of a model also viewed.
         *
         * `minimum_visitors` leaves out a model that fewer of those visitors
         * viewed, so the ranking never reveals what one or two people looked
         * at.
         *
         * `max_visitors` limits how many of the model's most recent visitors
         * are read. The query reads every view of each visitor, which gets
         * slow for a popular model. When `null`, every visitor is read.
         */
        'also_viewed' => [

            'minimum_visitors' => 3,

            'max_visitors' => 1_000,

        ],

        /*
         * `recommended()` and `recommendedFor()` start from the models a
         * viewer recently viewed, and rank what other visitors of those
         * models also viewed. They use `also_viewed.minimum_visitors` too,
         * so a recommendation never reveals what one or two people looked
         * at.
         *
         *   max_seeds     how many of the viewer's most recently viewed
         *                 models to start from
         *   max_visitors  how many of the most recent visitors of each of
         *                 those models are read, or `null` to read them all
         *   half_life     a model the viewer viewed this long ago counts half
         *                 as much as one viewed just now
         *   similarity    `cosine` keeps a model that nearly everyone views
         *                 from topping every list, `count` ranks by visitors
         *                 in common, as `alsoViewed()` does
         */
        'recommendations' => [

            'max_seeds' => 20,

            'max_visitors' => 500,

            'half_life' => '7d',

            'similarity' => 'cosine',

        ],

        /*
         * When enabled, `alsoViewed()` and `recommended()` read a few rows
         * from the pairs table instead of every view of every visitor. The
         * table keeps, for every model, the models its visitors also viewed.
         * Publish its migration with the `eloquent-viewable-pairs` tag and
         * schedule `views:pairs`.
         *
         *   period     only the views within this period are paired
         *   max_pairs  how many models are kept per model, the ones it
         *              shares the most visitors with
         *
         * A call with a period, a collection or a filter skips the table and
         * reads the views table. Views recorded since the last run of
         * `views:pairs` are not in the table yet.
         */
        'pairs' => [

            'enabled' => false,

            'table' => 'view_pairs',

            'period' => '90d',

            'max_pairs' => 100,

        ],

        /*
         * `trending()` and `orderByTrending()` weigh each view by its age,
         * so recent views count more.
         *
         *   curve      how a view loses weight: `null` for the built-in
         *              curve, or a class implementing
         *              `Querying\Ranking\DecayCurve`, resolved from the
         *              container
         *   half_life  how long the built-in curve takes to halve the
         *              weight of a view
         *   step       `1h` weighs views per hour, `1d` per day, and `auto`
         *              per hour, or per day when hours would exceed
         *              `max_steps`
         *   max_steps  the most steps one call may weigh. A call that would
         *              weigh more throws instead of running.
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
             * but no longer link a visitor across days. `viewer`, `context`
             * and the dimensions marked personal become null. Must not be
             * longer than `prune.after`.
             */
            'after' => null,

            /*
             * The columns anonymising clears or re-hashes: any of `visitor`,
             * `viewer`, `context` and `dimensions`. `dimensions` stands for
             * the dimensions marked personal.
             */
            'columns' => ['visitor', 'viewer', 'context', 'dimensions'],

        ],

        'prune' => [

            /*
             * Views older than this are deleted.
             */
            'after' => null,

        ],

        /*
         * How many views one statement reads or writes in `views:anonymise`,
         * `views:prune`, `views:purge-bots`, `views:recount`, `views:rollup`,
         * `views:maintain` and the `views:dimensions` backfill. The `--chunk`
         * option of a command overrides it for one run.
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
             * Dimensions from `dimensions.definitions` to fold per value,
             * with the tiers and groupings above, so `countBy()` reads their
             * history from the rollups. Each bucket keeps the dimension's top
             * values and folds the rest into `other`. A dimension left out is
             * only counted while its views are in the views table.
             */
            'dimensions' => [

                // 'source',

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
    | Milestones
    |--------------------------------------------------------------------------
    |
    | Thresholds a counter column can cross, such as a post's 10,000th view.
    | Each recount compares the columns it writes with them and dispatches
    | `ViewMilestoneReached` once per model and threshold. They need the
    | migration published with `--tag=eloquent-viewable-milestones`.
    |
    */
    'milestones' => [

        /*
         * The table that keeps the highest count each model crossed a
         * threshold at, so a threshold never fires twice.
         */
        'table' => 'view_milestones',

        /*
         * The thresholds of each counter column in `querying.counters`, in
         * ascending order. A column with a `period` goes up and down, so it
         * cannot have milestones. For example:
         *
         *   Post::class => [
         *       'views_count' => [100, 1_000, 10_000],
         *   ],
         */
        'thresholds' => [],

    ],

    /*
    |--------------------------------------------------------------------------
    | Spikes
    |--------------------------------------------------------------------------
    |
    | `views:detect-spikes` compares each model's views in the last closed
    | window with the same window on past days or weeks, and dispatches
    | `ViewsSpiked` once when a model takes off, `ViewsDropped` when it
    | collapses, and `ViewsSettled` once it is back to normal. Schedule it as
    | often as the window is wide. It needs the migration published with
    | `--tag=eloquent-viewable-spikes`.
    |
    */
    'spikes' => [

        /*
         * The table that keeps a row per model while it spikes or drops.
         */
        'table' => 'view_spikes',

        /*
         * The models to watch, each with its own settings. Every setting may
         * be left out for its default. For example:
         *
         *   Post::class => [
         *       'window' => '1h',        // whole hours, or `1d`
         *       'seasonality' => 'week', // `day` or `week`
         *       'samples' => 4,          // how many past windows to compare with
         *       'threshold' => 3.0,      // the z-score a spike starts at
         *       'minimum' => 50,         // views below which nothing counts
         *       'drops' => false,        // also watch for drops
         *       'cooldown' => '6h',      // how long it stays normal before it settles
         *   ],
         */
        'types' => [],

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
         *            under a random salt that is replaced at the start of
         *            every `fingerprint.rotation` window, so no cookie is set
         *            and a guest is a new visitor in every window; signed-in
         *            models get the `viewer` id
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
             * How long one salt is used: `day`, `week` or `month`. The salt
             * is replaced at the start of each, weeks starting on Monday, so
             * `unique()` counts a guest once per window. A longer window
             * keeps unique counts exact over that window, but keeps one
             * person linkable across all the views they make in it.
             */
            'rotation' => 'day',

            /*
             * The cache store the salt is kept in. Every server that
             * records views must share it, or each hashes under its own salt
             * and one visitor counts once per server. When `null`, the
             * application's default cache store is used.
             */
            'store' => null,

            /*
             * The cache key prefix the salt is kept under.
             */
            'key' => 'cyrildewit.eloquent-viewable.fingerprint',

        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Presence
    |--------------------------------------------------------------------------
    |
    | Who is looking at something right now: `views($post)->activeVisitors()`,
    | `Views::live()->top()` and the live counter of the beacon. A visitor is
    | active from the moment a view of theirs passes the guards, and stays
    | active while the page sends a heartbeat. A cooldown or the throttle does
    | not stop either. Kept apart from the views, so it works with any store.
    |
    */
    'presence' => [

        /*
         * Off by default. Reading a live count while it is off throws, so a
         * forgotten setting is not mistaken for an empty page.
         */
        'enabled' => false,

        /*
         * Where presence is kept: `redis`, `array`, which only lives as long
         * as the process and suits tests, or `null`. Register your own driver
         * with `PresenceManager::extend()`.
         */
        'driver' => 'redis',

        /*
         * How long a visitor stays active after they were last seen, in
         * seconds. `within()` can narrow it for one read, never widen it.
         */
        'window' => 300,

        /*
         * How the `redis` driver counts visitors. `exact` stores every active
         * visitor, so the count is exact and `leave()` works. `approximate`
         * lets Redis estimate the count per minute, which keeps memory flat
         * on very busy sites. The estimate is typically off by less than 1%,
         * it can include visitors from up to a minute before the window, and
         * `leave()` does nothing.
         */
        'precision' => 'exact',

        /*
         * How often the beacon's live script tells the server the page is
         * still open, in seconds. Keep it well below the window.
         */
        'heartbeat' => 60,

        /*
         * Whether the heartbeat answers with the number of active visitors,
         * which the live script writes into every `[data-views-live]`
         * element on the page.
         */
        'expose_count' => false,

        /*
         * Whether the signed-in viewers are kept too, by their type and key,
         * for `live()->viewers()`. Off by default, because it keeps who is
         * looking rather than an anonymous id.
         */
        'viewers' => false,

        /*
         * The most recently seen viewables `live()->top()` ranks. One ranking
         * reads one count per candidate, so a higher value ranks more
         * viewables at the cost of more reads per call.
         */
        'max_candidates' => 1_000,

        'redis' => [

            /*
             * The Redis connection from `database.redis`. When `null`, the
             * default connection is used.
             */
            'connection' => null,

            /*
             * The prefix of every key. It is wrapped in a hash tag, so on a
             * Redis Cluster every key lives on the same node.
             */
            'prefix' => 'eloquent-viewable:live',

        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Cooldown
    |--------------------------------------------------------------------------
    */
    'cooldown' => [

        /*
         * Where running cooldowns are kept: `session`, which needs a session
         * on the route, or `cache`, which does not. Register your own
         * driver with `CooldownManager::extend()`.
         */
        'store' => 'session',

        /*
         * The session key, or the cache key prefix, cooldowns are kept under.
         */
        'key' => 'cyrildewit.eloquent-viewable.cooldowns',

        'cache' => [

            /*
             * The cache store cooldowns are kept in when `store` is `cache`.
             * Every server that records views must share it, or a cooldown
             * started on one server is not seen by another. When `null`, the
             * application's default cache store is used.
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
            DimensionsCheck::class,
            SharedCacheCheck::class,
            ScheduleCheck::class,
            TrustedProxiesCheck::class,
            RedisStreamCheck::class,
            CrawlerShareCheck::class,
            ConfigurationCheck::class,
        ],

        /*
         * Counts per day how many attempts are recorded and how many each
         * guard refuses, so the doctor can tell when `IgnoreCrawlers` refuses
         * more than `crawler_share` of them, from 0 to 1. Off by default,
         * because it adds two cache calls to every attempt. The counts are
         * kept for a week, in a cache store every server must share. When
         * `store` is `null`, the application's default cache store is used.
         */
        'sample' => [

            'enabled' => false,

            'store' => null,

            'key' => 'cyrildewit.eloquent-viewable.samples',

            'crawler_share' => 0.5,

        ],

    ],

];
