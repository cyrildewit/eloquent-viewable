# Release Notes

All notable changes to `Eloquent Viewable` will be documented in this file.

The format is based on [Keep a Changelog](http://keepachangelog.com/en/1.0.0/)
and this project adheres to [Semantic Versioning](http://semver.org/spec/v2.0.0.html).

## [Unreleased]

Version 9 adds dashboards, rankings, viewer tracking, cookieless visitors and a Redis buffer, and reorganises the
package into modules. See the [upgrade guide](UPGRADING.md#upgrading-from-v800-to-v900) for the steps to upgrade.

### Added

#### Recording

- Added the `views` route middleware, `Http\Middleware\RecordViews`, which records the model bound to a route once a `GET` request gets a successful response. Route parameters or model classes pick what to record, `collection`, `cooldown` and `queue` options apply, and `RecordViews::using()` builds the middleware string
- Added the beacon, which records views of pages served from a full-page cache. Turn it on with `recording.beacon.enabled` and print the `@viewsBeacon($post)` Blade directive; the page posts to a signed route once it has loaded. `Http\Beacon::url()` builds the URL for a script of your own
- Added recording guards: the `recording.guards` config list, the `Recording\Contracts\RecordingGuard` contract for your own, and the new `IgnorePrefetch` guard, on by default, and `IgnoreGlobalPrivacyControl` guard, which honours `Sec-GPC: 1`
- Added the `IgnoreMissingUserAgent` and `IgnoreHeadRequests` guards, on by default, which drop requests without a user agent and `HEAD` requests
- Added the `ThrottleVisitors` guard, off by default, which caps the views one visitor records per minute across every model. Configure it under `recording.throttle`
- Added the `IgnoreBursts` guard, on by default, which refuses a visitor that opens more than 8 different models within 2 seconds, and blocks them for 2 minutes. It counts per visitor id and per network and user agent, so a bot that drops its cookie is caught too. Configure it under `recording.bursts`. `Recording\Events\BurstDetected` is dispatched when a block starts
- `recording.ignored_ip_addresses` accepts CIDR ranges such as `10.0.0.0/8`
- Added `Views::attempt()`, which records like `record()` and returns a `Recording\Data\RecordResult` saying whether the view was stored or queued, or which guard skipped it. `Recording\Events\ViewSkipped` is dispatched when a guard refuses a view
- Added `Recording\Events\ViewAttempted`, dispatched for every view the guards have judged with its `RecordResult`, in the request that made it, also when the write is queued. It is only built when something listens. `RecordResult` serialises to JSON with the guard named by its class
- Added a Debugbar collector: with `fruitcake/laravel-debugbar` 4.4 or newer installed, a **Viewable** tab lists the views stored, queued and skipped in the request, with the guard that refused each. Turn it off with `debugbar.collectors.eloquent_viewable`
- Added `Views::context(?array $context)` and a nullable `context` JSON column to store extra data with a view
- Added store drivers, picked by `recording.store.driver`: `database`, the default, `redis`, `array` and `null`. Add your own `Recording\Contracts\ViewStore` with `StoreManager::extend()`
- Added the `redis` store driver, which buffers views in a Redis stream and lands them in the views table in batches through the `views:flush` command or `Recording\Jobs\FlushBufferedViewsJob`. Needs Redis 7 or newer and phpredis or Predis 3.3 or newer
- Added cooldown stores, picked by `cooldown.store`: `session`, the default, and `cache`, which also works without a session. Add your own with `CooldownManager::extend()`

#### Viewers and visitors

- Added nullable `viewer_type` and `viewer_id` columns, the `recording.viewer.enabled` and `recording.viewer.guard` config options, and `Views::viewedBy()`, to link a view to the signed-in model and count the views of one model
- Added the `whereViewedBy()`, `whereNotViewedBy()`, `whereViewedByVisitor()` and `whereNotViewedByVisitor()` scopes
- Added the `Concerns\HasViewHistory` trait with `viewed()`, `hasViewed()` and `lastViewedAt()`, and a `viewer` relation and `byViewer()` and `byVisitor()` scopes on the `View` model
- Added the `visitor.identity` config option: `cookie`, the default, `viewer`, which counts one account as one visitor on every device, and `fingerprint`, which counts guests without a cookie by a hash that rotates daily
- Added the `visitor.cookie.lifetime` config option, in minutes. It still defaults to five years
- Added `forgetViewHistory()`, `anonymiseViewHistory()` and `exportViewHistory()` to `Concerns\HasViewHistory`, and the `views:forget-viewer` and `views:forget-visitor` commands, to erase or export the views of one person. They reach views in the Redis buffer, forget the remembered counts and recount the counter columns of the models touched, and dispatch `Erasure\Events\ViewHistoryForgotten`, `ViewHistoryAnonymised` and `ViewHistoryExported`. Rollups are left as they are

#### Querying

- Added `countByInterval()`, which returns a gap-filled `Querying\Series\ViewSeries` of counts per hour, day, week, month or year, with `labels()`, `values()`, `peak()`, `average()` and JSON output. `Views::timezone()` aligns its buckets to another timezone, and `querying.max_intervals` caps the number of buckets
- Added `Views::compare()` and `Period::previous()`, which compare a period with the one before it
- Added `Views::countByCollection()`, the counts of every collection at once
- Added `Views::top()`, a ranking of the most viewed models across every type or within one
- Added `Views::alsoViewed()`, a ranking of what the visitors of one model also viewed, bounded by the `querying.also_viewed.minimum_visitors` and `querying.also_viewed.max_visitors` config options. A view source of your own supports it by implementing `Querying\Contracts\RanksAlsoViewed`
- Added `Views::forViewables()` and `counts()`, which count a set of models you already have in one query
- Added `Views::returning()` and `Views::countByFrequency()`, which count the visitors who viewed a model on two days or more within the period, and how many visitors viewed on one day, on two, and so on, as a `Querying\Frequency\VisitFrequency` with `new()`, `returning()`, `total()`, `returningShare()` and JSON output. Views without a visitor and anonymised views are left out. A view source of your own supports them by implementing `Querying\Contracts\CountsVisitFrequency`
- Added the `whereViewsCount()` and `whereUniqueViewsCount()` scopes
- Added `Period::parse()`, route model binding for `Period`, and an optional timezone argument on the relative `Period` constructors
- Added `Views::forgetCache()` and `Views::flushCache()` to forget remembered counts, on every cache store
- Added count sources, picked by `querying.source.driver`. Add your own `Querying\Contracts\ViewSource` with `SourceManager::extend()`, and a bucket grammar for another database driver with `Querying\Grammars\GrammarRegistry`
- Added the `Querying\Contracts\SubquerySource` contract, which a source implements so the scopes can read from it. A source that does not throws `Querying\Exceptions\UnsupportedBySource` from a scope
- Added the `Querying\Contracts\IdentifiesSource` contract, whose `cacheIdentity()` keeps the remembered counts of a source apart per setting
- Added `Views::trending()` and the `orderByTrending()` and `withTrendingScore()` scopes, which rank models by views weighed by their age, so recent views count more. `Entry` has a `score`. Configure them under `querying.trending`
- Added the `Querying\Ranking\DecayCurve` contract with the `ExponentialDecay`, `LinearDecay` and `Window` curves, to choose or write how a view loses weight
- Added the `Querying\Contracts\RanksTrending` and `TrendingSubquerySource` contracts, which a source of your own implements to support `trending()` and the trending scopes
- Added personal recommendations: `recommended()` on `Concerns\HasViewHistory` and on `Views`, for the viewer `viewedBy()` names or the current visitor, and the `recommendedFor()` scope, which keeps the recommended models of a query ordered by a selected `recommendation_score`. They rank what the visitors of the viewer's most recent views also viewed, weighed by recency and by cosine similarity, leave out what the viewer viewed unless `includeSeen` is passed, and give each `Querying\Recommendations\Recommendation` the views it came from in `because`. Configure them under `querying.recommendations`. A view source of your own supports them by implementing `Querying\Contracts\RanksRecommendations`
- Added the pairs table, an opt-in table of the models that share the most visitors, which `alsoViewed()` and `recommended()` read instead of the `views` table when a call names no period or collection. `views:pairs` rewrites it and dispatches `Querying\Pairs\Events\ViewsPaired`. Configure it under `querying.pairs`; the migration is published under the `eloquent-viewable-pairs` tag

#### Presence

- Added presence, which counts who is looking right now: `views($post)->activeVisitors()`, and `live()` with `count()`, `counts()` for a set, `top()` for a ranking of what is being looked at, `viewers()` for the signed-in viewers and `within()` to narrow the window. It reads a viewable, a type or, through `Views::live()`, the whole site, within a collection when one is set. Turn it on with `presence.enabled`
- Added the `redis` presence driver, which stores every active visitor, or with `presence.precision` set to `approximate` estimates the count with a HyperLogLog per minute. All keys share one hash tag, so on a Redis Cluster they live on the same node. Writes Redis refuses are reported, not thrown. The `array` and `null` drivers and `Presence\Contracts\PresenceStore` with `PresenceManager::extend()` cover tests and stores of your own
- Added `Views::heartbeat()` and `Views::leave()`, which keep a visitor active without recording a view and stop counting them at once, and `@viewsBeacon($post, live: true)`, whose script sends a heartbeat every `presence.heartbeat` seconds while the page is visible and leaves when it closes. With `presence.expose_count` the heartbeat answers with the count, which the script writes into `[data-views-live]` elements and dispatches as a `views:live` event. `Http\Beacon::presenceUrl()` and `leaveUrl()` build the signed URLs
- Added the `Recording\Contracts\LimitsRepeats` marker interface, implemented by `EnforceCooldown` and `ThrottleVisitors`. A view such a guard refuses is skipped only once every other guard allowed it, and still keeps its visitor active; a heartbeat never asks these guards
- Added `RecordResult::$present`, also in its JSON and the Debugbar tab, which says whether an attempt kept the visitor active
- Added `present()`, `assertPresent()`, `assertNotPresent()` and `assertLeft()` to `Views::fake()`

#### Retention

- Added retention: `views:anonymise` anonymises views older than `retention.anonymise.after`, `views:prune` deletes views older than `retention.prune.after`, and `views:maintain` runs every step from one scheduler line. The migration is published under the `eloquent-viewable-retention` tag
- Added rollups: `views:rollup` folds views into day, month or other tiers per grouping, and the `rollup` source reads them, so history and fast all-time counts outlive deleted views. The migration is published under the `eloquent-viewable-rollups` tag
- Added custom rollups, classes that extend `Querying\Rollups\Rollup` with a filter, tiers, groupings and one dimension, listed under `retention.rollups.custom`. `Views::rollup()` reads one and `Views::countByDimension()` counts per value of its dimension, through the new `Contracts\FiltersViews` and `Querying\Contracts\CountsByDimension` contracts
- Added counter columns: `querying.counters` lists columns on your own tables that `views:recount` fills with a view count, and `views:maintain` runs it
- Added `--before` to `views:prune`, for an application that drops partitions of the `views` table itself
- Added `views:purge-bots`, which deletes the views inside a burst, the views `IgnoreBursts` would have refused, and folds the rollups again. Views of a signed-in viewer are kept unless `--include-viewers` is passed, and `--whole-visitor` deletes every view of a visitor with several bursts. Dispatches `Retention\Events\BotViewsPurged`. Rollups expose the new `Querying\Rollups\Contracts\Refolder` contract for it
- Added `--max-seconds` to `views:maintain`, `views:rollup`, `views:anonymise`, `views:prune` and `views:recount`. Once the time is up, a run finishes the bucket, day or chunk in progress and stops, and the next run carries on from there
- Added `Maintenance\Jobs\MaintainViewsJob`, which runs what `views:maintain` runs for at most `maxSeconds` and queues itself again while there is work left, for hosts that cut long scheduled commands off
- `views:recount` only recounts the models whose counts can have changed since the last recount when the retention migration is installed. `--full` recounts every model, as does the run after `views:purge-bots`, and `views($post)->destroy()` recounts the post's columns right away
- A maintenance run renews its lock while it works, so a run longer than an hour no longer lets a second run start beside it
- Anonymising reads each day once instead of once per chunk, and sets at most a hundred visitors per statement, which made anonymising a day two to six times faster in the benchmarks
- Added the `Retention\Events\ViewsAnonymised`, `ViewsPruned` and `Querying\Rollups\Events\ViewsRolledUp` events, and the `RetentionNotInstalled`, `RollupsNotInstalled`, `ResolutionUnavailable` and `LockUnavailable` exceptions

#### Doctor

- Added `views:doctor`, which checks the setup and says what to fix: the views table, its columns and indexes, the optional indexes the config relies on, shared cache stores for cooldowns, the throttle, the burst guard and the fingerprint salt, the scheduler running `views:maintain` or `MaintainViewsJob` and `views:flush`, trusted proxies when the visitor's IP address is read, the backlog of the Redis stream, and settings that undo each other. `--strict` fails on warnings, `--only` runs some checks and `--json` prints the findings
- Added the `doctor.checks` config list and the `Doctor\Contracts\Check` contract for checks of your own
- Added guard sampling, off by default under `doctor.sample`, which counts the attempts each guard refuses so `views:doctor` can warn when `IgnoreCrawlers` refuses more than `doctor.sample.crawler_share` of them
- Added `RedisStreamStore::backlog()`, which describes the views waiting in the Redis stream

#### Models and testing

- Added the `models.view.class` config option to use your own `View` model
- Added `shouldRemoveViewsOnDelete()` to the `Viewable` contract
- Added `Views::fake()` with `assertRecorded()`, `assertNotRecorded()`, `assertNothingRecorded()`, `assertForgotten()` and `recorded()`. The scopes throw `UnsupportedBySource` under the fake, apart from `recommendedFor()`, which reads the fake
- Added `View::factory()` with the `fromVisitor()`, `inCollection()`, `viewedAt()`, `by()` and `withContext()` states
- Added the `Exceptions\EloquentViewableException` marker interface, implemented by every exception the package throws, and the `InvalidConfiguration`, `InvalidViewable`, `InvalidViewer` and `InvalidTimezone` exceptions
- Added Laravel Boost guidelines and the `eloquent-viewable-development` skill in `resources/boost`, which `boost:install` offers to coding agents

### Changed

- `Period` is half-open: the start is included and the end is excluded, so a view recorded exactly at the end no longer counts
- `Period` converts bounds built in another timezone to the application timezone
- The migration stub creates a composite `(viewable_type, viewable_id, viewed_at)` index, an index on `viewed_at` and the `viewer` and `context` columns. Existing installations add them with the migration in the upgrade guide
- The config file is grouped by module: `cache` moved to `querying.cache`, `queue` to `recording.queue`, `ignored_ip_addresses` to `recording.ignored_ip_addresses` and `visitor_cookie_key` to `visitor.cookie.name`. Config values are validated when read and throw `InvalidConfiguration` when invalid
- The crawler, Do Not Track, IP address and cooldown checks are guards in `recording.guards`. The defaults keep the v8 behaviour and also skip prefetched pages, `HEAD` requests and requests without a user agent
- Classes moved into module namespaces, and `CreateView`, `StoreView`, `ViewRecordException` and `PendingView` were renamed. The upgrade guide has the full table
- `Visitor::DNT` is renamed to `Visitor::DoNotTrackHeader`
- `Recording\Contracts\RecordsViews::handle()` receives a `Data\ViewRecord` and returns `void`
- `Recording\Events\ViewRecorded` carries the `Data\ViewRecord` as `$record` instead of the model as `$view`, and no longer uses `SerializesModels`
- Views are written through the query builder, so `View` model events no longer fire when a view is stored
- `Visitors\Contracts\Visitor` gained `viewer()`, `userAgent()`, `hasGlobalPrivacyControl()` and `isPrefetch()`. The shipped `Visitor` constructor takes `Support\Config`, the cookie jar and the auth factory, and no longer takes a `CrawlerDetector`
- `Crawlers\Contracts\CrawlerDetector::isCrawler()` receives the user agent to judge
- `CacheKey` moved to `Querying\Cache\CacheKey` and `make()` takes a `ViewsQuery`. Remembered counts are recalculated once after upgrading, and again when the source driver or its connection changes
- `Views::destroy()` and deleting a model forget the counts remembered of it
- Cooldowns are keyed by visitor id and stored in a new format, so cooldowns running at upgrade end early once
- `Models\View` prefers its own `$table` and `$connection` properties over the config
- The `withViewsCount()` and `orderByViews()` scopes count with a correlated subselect from the source instead of `withAggregate()`, with the same results
- The `Views` constructor takes the visitor, `Recording\Recorder`, `Querying\Reader`, `Recording\Actions\DestroyViews` and `Querying\Cache\CacheVersions`
- `Recording\Jobs\RecordViewJob` has no static `dispatch()` method. Use `dispatch(new RecordViewJob($record))`
- The package requires the `illuminate/bus`, `illuminate/container`, `illuminate/queue` and `illuminate/routing` components and no longer uses `Illuminate\Foundation`
- The migration stub is published from `database/migrations/`, with no change for existing installations
- Development only: the test suite runs against SQLite, MySQL, MariaDB and Postgres, including models keyed by UUID and ULID, and a phpbench suite in `benchmarks/` times the queries against millions of views

### Removed

- Removed the `Contracts\View` and `Contracts\Views` interfaces. Extend `Models\View` and name it in `models.view.class`. `Views` can no longer be replaced through the container
- Removed the `$removeViewsOnDelete` model property in favour of `shouldRemoveViewsOnDelete()`
- Removed `Visitors\Contracts\Visitor::isCrawler()`
- Removed the `ignore_bots` and `honor_dnt` config keys. List or unlist the guards in `recording.guards` instead
- Removed `CooldownManager::push()`

### Fixed

- Fixed a count remembered in a Redis cache store failing to read back with a `TypeError`, because Redis returns numbers as strings
- Fixed the `Views` facade carrying the viewable, period, collection and every other option of one call into the next within a request. See the [upgrade guide](UPGRADING.md#every-facade-call-starts-a-fresh-builder)
- Fixed the crawler detector judging every request in a long-running worker, such as Octane, by the user agent of the first request
- Fixed an Octane worker reading the package config it booted with, so a `config()` change made during a request was ignored
- Fixed `PeriodInterval::subtract()` mutating the date instance passed to it
- Fixed a visitor without a cookie getting a new id, and queueing another cookie, from every `Visitor` instance in the same request
- Fixed expired session cooldowns being pruned only for the viewable type being checked, so cooldowns for other types piled up in the session

## [v8.0.1]

### Changed

- `Visitor` now receives the cookie jar through its constructor (`Illuminate\Contracts\Cookie\QueueingFactory`) and reads the existing visitor cookie from the injected request, instead of going through the `Cookie` facade (breaking only for subclasses that override the constructor)
- The `views()` helper now throws an `InvalidArgumentException` when given a class name that does not implement `Contracts\Viewable`, instead of a `TypeError` from deeper inside the package

### Fixed

- Fixed soft deletes removing the views of a viewable model; `ViewableObserver` now skips soft deletes and only destroys views on a force delete, so a restored model keeps its view count. If you relied on a soft delete purging views, call `views($model)->destroy()` yourself
- Fixed expired cooldowns wiping every cooldown for the same viewable type; `CooldownManager` built the session key to forget from an empty `array_column()` result, so it removed the whole namespace instead of the one expired entry
- Fixed `Visitor::hasDoNotTrackHeader()` always returning `false`; it looked the header up as `HTTP_DNT`, which is the `$_SERVER` key, while Laravel's header bag exposes it as `DNT` (the `Visitor::DNT` constant now holds `'DNT'`)
- Fixed `orderByUniqueViews()` and `withViewsCount(unique: true)` ordering and counting by total views instead of distinct visitors; Laravel drops the extra select added inside a `withCount` constraint, so the distinct count is now passed as the aggregate expression

## [v8.0.0]

See the [upgrade guide](UPGRADING.md#upgrading-from-v703-to-v800) for detailed migration instructions.

### Added

- Added native type declarations across the public API

### Changed

- Raised the minimum PHP version to `^8.5`
- The `Views` contract now declares the existing `useVisitor()` method (breaking only for classes that implement `Contracts\Views` directly)
- Changed the `cooldown()` and `remember()` parameters on the `Views` contract to be typed `DateTimeInterface|int|null`
- Changed the `scopeWithinPeriod()` method on the `View` contract to declare a `void` return type
- Narrowed `Period::getStartDateTime()`/`getEndDateTime()` to return `?CarbonInterface`
- Marked the `InvalidPeriod` and `ViewRecordException` exceptions as `final` (breaking only for code that extends them)
- The `Viewable` contract now uses `@mixin \Illuminate\Database\Eloquent\Model` instead of redeclaring the `getKey()` and `getMorphClass()` methods
- The `View` contract now uses `@mixin \Illuminate\Database\Eloquent\Model` and declares the existing `scopeCollection()` method (breaking only for classes that implement `Contracts\View` directly)
- Modernized the `create_views_table` migration stub (typed properties and return types)
- Changed the `visitor` column in the `create_views_table` migration stub from `text` to `string` (`VARCHAR(255)`) so it can be indexed directly (only affects newly published migrations)
- Changed the default config `models.view.connection` and `cache.store` to `null`, so they now defer to the application's default database connection and cache store instead of reading `env('DB_CONNECTION')` and the deprecated `env('CACHE_DRIVER')` (only affects newly published config)
- Migrated the test suite from PHPUnit to [Pest](https://pestphp.com/) (development only; no impact on consumers)
- Redesigned `Period` as a `final`, immutable (`readonly`) value object; relative periods are now built with the new `Support\PeriodInterval` and `Support\PeriodAnchor` enums, and date parsing defers to `Carbon::make()` (the factory methods, `getStartDateTime()`/`getEndDateTime()`, and the cache-key format are unchanged)

### Removed

- Dropped support for Laravel 6 through 12 (`illuminate/*` now requires `^13.0`)
- Dropped support for Carbon 2 (`nesbot/carbon` now requires `^3.0`)
- Removed the `getStartDateTimeString()`, `getEndDateTimeString()`, `getStartDateTimestamp()` and `getEndDateTimestamp()` getters from `Period`
- Removed the `Period::PAST_*` and `Period::SUB_*` constants and the `sub()`, `subToday()` and `subNow()` static helpers
- Removed the `Period::getSubType()`, `getSubValue()`, `hasFixedDateTimes()`, `setStartDateTime()`, `setEndDateTime()`, `setFixedDateTimes()`, `setSubType()` and `setSubValue()` methods (`Period` is now immutable)

## [v7.1.1]

### Fixed

- Fixed cooldown pruning when the session uses JSON serialization, where `expires_at` is restored as a string instead of a Carbon instance

## [v7.1.0]

### Changed

- Add support for Laravel 13

## [v7.0.3]

### Changed

- Add support for Laravel 12

## [v7.0.3]

### Changed

- Allow Carbon v3 as dependency

## [v7.0.2]

### Changed

- Add support for Laravel 11

## [v7.0.1]

### Changed

- Add support for Laravel 10
- Fixed deprecated variable notation in string

## [v6.1.0]

### Changed

- Add support for Laravel 9

## [v6.0.2]

### Fixed

- Revert breaking change of `remember` method in `Views` contract. The `$lifetime` variable has now a default value of `null`.

## [v6.0.1]

### Fixed

- Revert breaking change of `remember` method in `Views` class. The `$lifetime` variable has now a default value of `null`.

## [v6.0.0]

### Added

- Added `bool` return typehint to `record` method in `Views` contract.
- Added `void` return typehint to `destroy` method in `Views` contract.
- The `ViewRecordException` will be thrown when trying to record a view for a viewable type.
- The `ViewRecorded` event will be fired when a new view is recorded.
- Added `Views` typehint to global `views()` function.
- Added `bool` return typehint to `isCrawler` method in `CrawlerDetector` contract.

### Changed

- Set required PHP versions in `composer.json` to `^7.4|^8.0`.
- The creating of the `View` instance has been moved into its own method `protected function createView(): View`.
- The `$viewable` argument of the `forViewable` method in `Views` contract cannot be nullable anymore.
- Changed the method arguments of `orderByViews` and `orderByUniqueViews` query scope in `Viewable` contract and `InteractsWithViews` trait.
- Changed the method arguments of `withViewsCount` query scope in `InteractsWithViews` trait.
- Added nullable `Period` class typehint to `$period` argument of `period` method in `Views` contract.
- Made `$name` argument nullable in `Views` contract.
- Changed return typehint of `ip` method in `Visitor` contract to `?string`.
- Change `DateTime` typehint to `DateTimeInterface` in `InvalidPeriod` exception.

### Removed

- Removed `lifetime_in_minutes` option from config file.

### Fixed

- Fixed `count` method of `Views` class to count all views, including the collections (#241).

## [v5.2.1] (2020-09-22)

### Changed

- Add support for Laravel 8

## [v5.2.0]

### Fixed

- Use `CyrildeWit\EloquentViewable\Contracts\Views` to resolve Views instance from container.

## [v5.1.0]

### Changed

- Remove default value (`null`) for viewable in `views()` helper.

## [v5.0.0]

### Added

- Added `Views` contract.
- Added `Visitor` contract.
- Added the `Visitor` class which represents the current visitor.
- Added protected `getViewableTypeSlug()` to `CacheKey::class`.
- Added `orderByViews` query scope to `Viewable` trait.
- Added `orderByUniqueViews` query scope to `Viewable` trait.
- Added `withViewsCount` query scope to `Viewable` trait.

### Changed

- The `CyrildeWit\EloquentViewable\Viewable` trait has been renamed to `CyrildeWit\EloquentViewable\InteractsWithViews`.
- Renamed `session.key` to `cooldown.key` in configuration file.
- Changed the `Views` class constructor arguments. Added the `Visitor` as first argument and removed `VisitorCookieRepository $visitorCookieRepository`, `CrawlerDetector $crawlerDetector` and `IpAddressResolver $ipAddressResolver`.
- Replaced calls to `$this->crawlerDetector` in `Views` with new `Visitor` class implementation.
- Replaced calls to `requestHasDoNotTrackHeader` in `Views` with new `Visitor` class implementation.
- Changed type of primary key from `increments` to `bigIncrements` in `views` table migration.
- Bumped minimum requirements for Laravel framework components to `^6.0|^7.0`.
- Removed check for provided viewable type in `getConnectionName()`, `getDatabaseName()`, `getModelSlug()` and `getKeySlug` in `CacheKey::class`.
- Renamed `ViewSessionHistory` class to `CooldownManager`.
- Changed constructor of `CooldownManager` class. The `Illuminate\Contracts\Config\Repository` has been added as first parameter and the cooldown key is now retrieved from this instance.
- The `Views` class now implements the `Views` contract.
- The constructor of the `Views` class has been changed.
- Moved `Facades/Views` to `src/` and renamed it to `ViewsFacade`.
- The global `views()` helper now supports viewable types.

### Removed

- Dropped support for `nesbot/carbon` ^1.22.
- Removed the deprecated `overrideIpAddress` method from the `Views` class.
- Removed the deprecated `overrideVisitor` method from the `Views` class.
- Removed `requestHasDoNotTrackHeader` method from `Views` class.
- Removed `$viewableType` argument from constructor of `CacheKey::class`.
- Removed static `fromViewableType` method from `CacheKey::class`.
- Removed `HeaderResolver` contract and class.
- Removed `IpAddressResolver` contract and class.
- Removed `uniqueVisitor()` scope from `View` model.
- Removed `Enums\SortDirection` class.
- Removed `OrderByViewsScope` class.
- Removed the `countByType` method from the `Views` class.
- Removed the `VisitorCookieRepository` (logic is moved to the `Visitor` class).

## [v4.1.1] (2019-10-18)

### Fixed

- Update required dependencies in composer.json to adhere to new Laravel 6 version scheme

## [v4.1.0] (2019-09-03)

### Changed

- Add support for Laravel 6

## [v4.0.0] (2019-07-01)

### Added

- Added `SortDirection` enum class that contains a `DESCENDING` and `ASCENDING` constant
- Added `OrderByViewsScope` class that can order a query by views based on some options
- Added `collection($collection)` query scope to `View` model
- Added the ability to pass a collection to the `orderByViews` and `orderByUniqueViews` query scope
- Added a new `CacheKey` class with a new improved approach to making keys for the cache
- Added `getStartDateTimestamp` and `getEndDateTimestamp` methods to the `Period` class for internal use
- Added `string $collection = null` argument to `push`, `createNamespaceKey` and `createViewableKey` methods in `ViewSessionHistory`

### Deprecated

- Deprecated the `overrideIpAddress` method of the `Views` class. Please use the new `useIpAddress(string $address)` method instead.
- Deprecated the `overrideVisitor` method of the `Views` class. Please use the new `useVisitor(string $visitor)` method instead.

### Changed

- Replaced inner code of the `orderByViews` and `orderByUniqueViews` query scope with the new `OrderByViewsScope` class
- Removed the `string` type declaration from the `getTable` method in the `View` model class [#165]([#165](https://github.com/cyrildewit/eloquent-viewable/pull/165))

### Removed

- Removed the `Support\Key` class with its references

## [v3.2.0] (2019-03-03)

### Added

- Add support for Laravel 5.8

### Changed

- Use String and Array classes instead of the helper functions

## [v3.1.0] (2019-01-29)

### Fixed

- Fixed the ability to pass an integer to the `delayInSession` method without getting an error
- Type cast the cached views count otherwise PHP's type hint will fail

### Added

- Added the ability to override the visitor's unique ID that's used to distinguish unique views
- Added the ability to specify a cache store that should be used by this package

## [v3.0.2] (2018-12-25)

### Fixed

- The method `delayInSession` isn't working properly

## [v3.0.1] (2018-12-25)

### Fixed

- Publishing package migrations results in error (#133)

## [v3.0.0] (2018-12-17)

### Added

- Added `Views` class with facade
- Added `IpAddressResolver` contract with implementation
- Added `HeaderResolver` contract with implementation
- Added `VisitorCookieRepository` class
- Added global helper `views`
- Added `collection` column to views table schema
- Added `withinPeriod` scope to `View` model
- Added `uniqueVisitor` scope to `View` model

### Changed

- Bumped minimum required PHP version to ^7.1
- Require viewable models to implement the `Viewable` contract
- Added global `views()` helper
- Remove IP address as fallback for visitor cookie when it doesn't exists
- Changed the `isBot` method name to `isCrawler` in `CrawlerDetector` contract and updated the `CrawlerDetectAdapter`
- Changed the visibility of the `$detector` property from `protected` to `private`
- Add support for `5.7.*` of `illuminate/config` to `composer.json`
- Moved config file from `publishable/config` to `config/`
- Replace `create_views_table` stub with real migration file and load it inside the service provider
- Allow strings to be passed to the constructor of the `Period` class
- Extracted key generation logic from `Period` class to the `Key` class

### Removed

- Removed the `ViewTracker` class
- Removed the `ViewableService` class
- Removed the `ProcessView` job
- Removed the `update_views_table` migration file from `resources/database/migrations`
- Removed `illuminate/bus` as dependency
- Removed `illuminate/queue` as dependency
- Removed `illuminate/routing` as dependency

## [v2.5.0] (2018-12-03)

### Fixed

- `orderByViewsCount` scope doesn't adhere to connection prefix

## [v2.4.3] (2018-10-21)

### Fixed

- Data too long for column `visitor`

## [v2.4.2] (2018-10-21)

### Fixed

- ProcessView job is always failing

## [v2.4.1] (2018-09-12)

### Fixed

- View is saved before ProcessViews job is ran

## [v2.4.0] (2018-09-11)

### Changed

- Add support for Laravel 5.7

### Deprecated

- Deprecated the `CyrildeWit\Support\IpAddress` class
- Deprecated the `CyrildeWit\Viewtracker` class
- Deprecated the `scopeOrderByViewsCount` method in the `Viewable` trait
- Deprecated the `scopeOrderByUniqueViewsCount` method in the `Viewable` trait

## [v2.3.0] (2018-07-23)

### Added

- Add `orderByUniqueViewsCount` scope to `Viewable` trait

## [v2.2.0] (2018-07-23)

### Added

- Add the ability to add a delay between views from the same session ([#73](https://github.com/cyrildewit/eloquent-viewable/pull/73))

### Changed

- Caching is now turned off as default

## [v2.1.0] (2018-06-06)

This release accidentally contains no updates.

## [v2.0.0] (2018-05-28)

This major version contains some serious breaking changes! See the [upgrade guide](https://github.com/cyrildewit/laravel-page-view-counter/blob/2.0/UPGRADING.md) for more information!

### Added

- Added `visitor` column to the  `create_views_table` migration stub

### Changed

- Changed the package name from `cyrildewit/laravel-page-view-counter` to `cyrildewit/eloquent-viewable`
- Renamed the `HasPageViewCounter` trait to `Viewable`
- Renamed the `PageViewCounterServiceProvider` class to `EloquentViewableServiceProvider`
- Changed the namespace from `CyrildeWit\PageViewCounter\xxx` to 'CyrildeWit\EloquentViewable'
- Added new options to the config file and changed the structure
- Replaced the `addPageView` method with `addView` in the `Viewable` trait
- Replaced all `getPageViews<suffix>` methods with `getViews` in the `Viewable` trait

### Removed

- Removed the `addPageViewThatExpiresAt` method from the `Viewable` trait
- The DateTransformer functionality has been removed
## [v5.2.1] (2020-09-22)
[Unreleased]: https://github.com/cyrildewit/eloquent-viewable/compare/v8.0.1...HEAD
[v8.0.1]: https://github.com/cyrildewit/eloquent-viewable/compare/v8.0.0...v8.0.1
[v8.0.0]: https://github.com/cyrildewit/eloquent-viewable/compare/v7.1.1...v8.0.0
[v7.1.1]: https://github.com/cyrildewit/eloquent-viewable/compare/v7.1.0...v7.1.1
[v7.1.0]: https://github.com/cyrildewit/eloquent-viewable/compare/v7.0.3...v7.1.0
[v7.0.3]: https://github.com/cyrildewit/eloquent-viewable/compare/v7.0.2...v7.0.3
[v7.0.2]: https://github.com/cyrildewit/eloquent-viewable/compare/v7.0.1...v7.0.2
[v7.0.1]: https://github.com/cyrildewit/eloquent-viewable/compare/v6.1.0...v7.0.1
[v6.1.0]: https://github.com/cyrildewit/eloquent-viewable/compare/v6.0.2...v6.1.0
[v6.0.2]: https://github.com/cyrildewit/eloquent-viewable/compare/v6.0.1...v6.0.2
[v6.0.1]: https://github.com/cyrildewit/eloquent-viewable/compare/v6.0.0...v6.0.1
[v6.0.0]: https://github.com/cyrildewit/eloquent-viewable/compare/v5.2.1...v6.0.0
[v5.2.1]: https://github.com/cyrildewit/eloquent-viewable/compare/v5.2.0...v5.2.1
[v5.2.0]: https://github.com/cyrildewit/eloquent-viewable/compare/v5.1.0...v5.2.0
[v5.1.0]: https://github.com/cyrildewit/eloquent-viewable/compare/v5.0.0...v5.1.0
[v5.0.0]: https://github.com/cyrildewit/eloquent-viewable/compare/v4.1.1...v5.0.0
[v4.1.1]: https://github.com/cyrildewit/eloquent-viewable/compare/v4.1.0...v4.1.1
[v4.1.0]: https://github.com/cyrildewit/eloquent-viewable/compare/v4.0.0...v4.1.0
[v4.0.0]: https://github.com/cyrildewit/eloquent-viewable/compare/v3.2.0...v4.0.0
[v3.2.0]: https://github.com/cyrildewit/eloquent-viewable/compare/v3.1.0...v3.2.0
[v3.1.0]: https://github.com/cyrildewit/eloquent-viewable/compare/v3.0.2...v3.1.0
[v3.0.2]: https://github.com/cyrildewit/eloquent-viewable/compare/v3.0.1...v3.0.2
[v3.0.1]: https://github.com/cyrildewit/eloquent-viewable/compare/v3.0.0...v3.0.1
[v3.0.0]: https://github.com/cyrildewit/eloquent-viewable/compare/v2.3.3...v3.0.0
[v2.4.3]: https://github.com/cyrildewit/eloquent-viewable/compare/v2.3.2...v2.4.3
[v2.4.2]: https://github.com/cyrildewit/eloquent-viewable/compare/v2.3.1...v2.4.2
[v2.4.1]: https://github.com/cyrildewit/eloquent-viewable/compare/v2.4.0...v2.4.1
[v2.4.0]: https://github.com/cyrildewit/eloquent-viewable/compare/v2.3.0...v2.4.0
[v2.3.0]: https://github.com/cyrildewit/eloquent-viewable/compare/v2.2.0...v2.3.0
[v2.2.0]: https://github.com/cyrildewit/eloquent-viewable/compare/v2.1.0...v2.2.0
[v2.1.0]: https://github.com/cyrildewit/eloquent-viewable/compare/v2.0.0...v2.1.0
[v2.0.0]: https://github.com/cyrildewit/eloquent-viewable/compare/v1.0.5...v2.0.0
