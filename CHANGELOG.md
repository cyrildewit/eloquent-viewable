# Release Notes

All notable changes to `Eloquent Viewable` will be documented in this file.

The format is based on [Keep a Changelog](http://keepachangelog.com/en/1.0.0/)
and this project adheres to [Semantic Versioning](http://semver.org/spec/v2.0.0.html).

## [Unreleased]

See the [upgrade guide](UPGRADING.md#upgrading-from-v800-to-v900) for detailed migration instructions.

### Added

- Added nullable `viewer_type` and `viewer_id` columns to the `create_views_table` stub, a polymorphic link from a view to the model that was signed in when it was recorded. Any Eloquent model can be a viewer; existing installations add the columns with the migration in the upgrade guide
- Added the `recording.viewer.enabled` and `recording.viewer.guard` config options. When enabled, every recorded view stores the model signed in on that auth guard, or the default guard for `null`. Off by default
- Added `Views::viewedBy(?Model $viewer)`, which credits a recorded view to the given model whether or not recording the viewer is enabled, and narrows `count()` and `countByInterval()` to the views that model made. `null` clears it
- Added the `whereViewedBy()`, `whereNotViewedBy()`, `whereViewedByVisitor()` and `whereNotViewedByVisitor()` scopes to `InteractsWithViews`, each with an optional period and collection, backed by `Querying\Scopes\WhereViewed`
- Added the `Concerns\HasViewHistory` trait for the viewer side, with `viewed()`, `hasViewed()` and `lastViewedAt()`
- Added the `viewer()` relation and the `byViewer()` and `byVisitor()` scopes to the `View` model, and `matching()` now applies the viewer of a `ViewsQuery`
- Added `Visitors\Contracts\Visitor::viewer()`, the signed-in model or `null` for a guest. The shipped `Visitor` reads it from the configured auth guard
- Added the `visitor.identity` config option. `cookie`, the default, keeps the random cookie id in the `visitor` column. `viewer` derives the id from the signed-in model when one is known, an HMAC of its type and key with `app.key`, so `unique()` counts one account as one visitor on every device and a cooldown holds across them; guests keep the cookie id. `Visitors\VisitorIdentity` resolves it, taking the key from the bound `Encrypter`, and `ofViewer()` gives the id of a model for the visitor-based scopes. An unknown value throws `InvalidConfiguration`
- Added `Support\ViewerKey` and `Exceptions\InvalidViewer`, thrown when a viewer's key is neither an integer nor a string, on record and on read alike, so an unsaved model as viewer never matches guest views
- Added a nullable `context` JSON column to the `create_views_table` stub and `Views::context(?array $context)`, which stores an array with the view. The `View` model casts it back to an array; the package never reads it
- Added `viewerType`, `viewerId` and `context` to `Data\ViewRecord`, carried through `toArray()`, `toPayload()` and `fromPayload()`
- Added `viewer` to `Support\ViewsQuery` and to the cache key digest, and `viewer` and `context` to `Recording\Data\ViewAttempt`
- Added the `by()` and `withContext()` states to `Database\Factories\ViewFactory`
- Added `countByInterval(Granularity $granularity)` to `Views`, returning a gap-filled `Querying\Series\ViewSeries` of `Bucket` objects per hour, day, week, month or year
- Added `labels()`, `values()`, `peak()`, `average()` and `toArray()` to `ViewSeries`, which is now `Arrayable` and `JsonSerializable`, plus a `label` on every `Bucket`, formatted by `Granularity::labelFormat()`
- Added `Views::timezone()`, which aligns the buckets of `countByInterval()` to the clock of a timezone identifier such as `Australia/Sydney` and re-anchors a relative period built without a zone of its own on that clock. The database shifts `viewed_at` before it truncates by fixed offsets computed in PHP, one per stretch between daylight saving transitions of either zone, so no driver needs zone tables. `ViewsQuery` carries the zone as `timezone`, `ViewSeries` exposes it as `timezone` and `ViewSeries::fill()` takes it as an optional fourth argument. The cache key includes it. `Views::fake()` honours it
- Added `Querying\Data\TimezoneConversion`, the conversion a bucket grammar receives, with the `from` and `to` zones, the period bounds and `segments()`, the list of `Querying\Data\OffsetSegment` fixed offsets to apply, and the `Querying\Grammars\Concerns\ConvertsByOffset` trait the shipped grammars build their `convertTimezone()` from
- Added `Support\Timezone`, a `DateTimeZone` that only accepts an identifier, and `Exceptions\InvalidTimezone`, thrown for an offset or an abbreviation by `Views::timezone()`, `Period::parse()` and the relative `Period` constructors
- Added `Period::parse()`, which reads a period from its string form: a shorthand such as `7d`, `3w`, `6m` or `1y`, counted back from midnight like `pastDays()`, or `90s`, `30min` or `12h`, counted back from now like `subHours()`, or a range of ISO 8601 bounds such as `2026-01-01..2026-02-01`, `2026-01-01T10:30:00..` or `..2026-02-01`. Takes an optional timezone. Anything else throws `InvalidPeriod`
- Added an optional timezone argument to every relative `Period` constructor, `Period::pastDays(7, 'Australia/Sydney')`, so a `past` period starts at midnight of that zone. The cache signature includes it
- `Period` now implements `UrlRoutable`: a `{period}` route parameter binds implicitly, an unreadable value responds with a 404, and `route()` renders a period through `getRouteKey()`, the shorthand for a relative period or the bounds around `..`
- Added `PeriodInterval::shorthand()`, `PeriodInterval::fromShorthand()` and `PeriodInterval::anchor()`, and `Support\RelativePeriod`, which keeps what a relative period was asked for, for internal use
- Added the `Support\Granularity` enum (`Hour`, `Day`, `Week`, `Month`, `Year`) for bucket sizes
- Added the `Support\ViewsQuery` value object describing the period, collection and uniqueness of a count
- Added the `Querying\Contracts\ViewSource` contract, read by `count()`, `countByInterval()` and the `withViewsCount()` and `orderByViews()` scopes, with `Querying\Sources\DatabaseSource` as the default implementation
- Added the `querying.source.driver` config option and `Querying\Sources\SourceManager`, which builds the source it names. The `database` driver ships; add one with `SourceManager::extend()`. An unregistered name throws `InvalidConfiguration`
- Added the `Querying\Scopes\WithViewsCount` and `Querying\Scopes\OrderByViews` classes behind the trait scopes of the same name
- Added the `Recording\Contracts\RecordingGuard` contract and the `recording.guards` config option, a list of guard classes every recorded view passes in order. The list is the only switch for a guard. `IgnoreCrawlers`, `IgnoreIpAddresses` and `Recording\Guards\EnforceCooldown` are listed out of the box; `IgnoreDoNotTrack` and `IgnoreGlobalPrivacyControl` ship commented out in the config file
- Added the `Recording\Contracts\RemembersRecordedViews` contract for guards that keep state about the views they let through. The recorder calls `remember()` once every guard has allowed the view and it is stored or queued, so `EnforceCooldown` no longer has to be listed last and a view another guard drops never starts a cooldown
- Added the `Cooldowns\Contracts\CooldownStore` contract and the `cooldown.store` config option. `Cooldowns\CooldownManager` builds the store it names: `session`, the default, or `cache`, which keeps cooldowns in the store named by `cooldown.cache.store`. Add a driver with `CooldownManager::extend()`. An unregistered name throws `InvalidConfiguration`
- Added `Cooldowns\Cooldown`, which builds the key a cooldown is kept under from the viewable, the visitor id and the collection
- Added `Recording\Events\ViewSkipped`, dispatched with the attempt and the guard that refused it
- Added `Views::attempt()`, which records like `record()` and returns a `Recording\Data\RecordResult` with `recorded`, `queued` and `skippedBy`, the guard that refused the view or `null`, plus `wasSkippedBy(string $guard)`. `record()` keeps returning `bool`
- Added `Visitors\Contracts\Visitor::userAgent()` and `hasGlobalPrivacyControl()`. The shipped `Visitor` joins the `User-Agent` header with the device headers a proxy adds, the same list the crawler detector library reads
- Added the `visitor.cookie.lifetime` config option, the lifetime of the visitor cookie in minutes. It was a constant of five years before and still defaults to that
- Added `Testing\ViewsFake` and `Facades\Views::fake()`, an in-memory stand-in for the store and the source with `assertRecorded()`, `assertNotRecorded()`, `assertNothingRecorded()`, `assertForgotten()` and `recorded()`. The scopes throw `Testing\Exceptions\UnsupportedInFake` under the fake
- Added `Recording\Stores\ArrayStore` and the `array` store driver
- Added the `redis` store driver and `Recording\Stores\RedisStreamStore`, which appends every recorded view to a Redis stream with one `XADD` and lands them in the views table in batches of one insert statement through a consumer group. Views are delivered at least once: a flusher that crashes between the insert and the acknowledgement has its batch landed again by the next flush that finds it idle. It works with phpredis and Predis and needs Redis 7 or newer. `illuminate/redis` is a suggested dependency, not a required one
- Added the `recording.store.redis` config options: `connection`, the `database.redis` connection holding the stream, `stream`, the stream key, `group`, the consumer group, and `landing`, the store driver flushed views land in, `database` by default. Naming `redis` as its own landing store throws `InvalidConfiguration`
- Added the `Recording\Contracts\BufferedViewStore` contract, a `ViewStore` with `flush(int $limit = 1000): int`, for stores that land their views later
- Added the `views:flush` command, which lands every buffered view in batches of `--batch` views and reports how many landed, and `Recording\Jobs\FlushBufferedViewsJob`, which does the same from a queue worker. Both go through `Recording\Buffering\Flusher` and refuse with `Recording\Exceptions\StoreIsNotBuffered` when the configured store does not buffer
- Added `Recording\Streams\ViewStream`, the stream behind the `redis` driver, and `Recording\Streams\Contracts\StreamClient` with a phpredis and a Predis implementation, which hide how each client orders the arguments and shapes the replies of the stream commands. `Recording\Exceptions\UnsupportedRedisClient` is thrown for a Redis connection that is neither, and `Recording\Exceptions\RedisStreamFailed` when the consumer group cannot be created or a stream entry is not a view record
- Added `ViewRecord::belongsTo(Viewable $viewable)`, whether the record is a view of the viewable, comparing keys as strings. A viewable without a key stands for every viewable of its type
- Added `Querying\Reader`, which reads through the bound `ViewSource`, owns the `remember()` cache and the interval cap, and fills the `ViewSeries`
- Added `Recording\Recorder`, which runs the guards and hands the record to the action or the queue and returns a `RecordResult`, and `Recording\Data\ViewAttempt`, the value object the guards receive
- Added the `Querying\Contracts\BucketGrammar` interface with `truncate()` and `convertTimezone()`, shipped grammars for SQLite, MySQL/MariaDB and Postgres, and the `Querying\Grammars\GrammarRegistry` registry for adding drivers
- Added `shouldRemoveViewsOnDelete()` to the `Viewable` contract, implemented by `InteractsWithViews` to return `true`. Override it to keep the views of a deleted model
- Added the `forViewable()` and `matching()` scopes and the `newQueryFor()` method to the `View` model
- Added `Database\Factories\ViewFactory` behind `View::factory()`, with the `fromVisitor()`, `inCollection()` and `viewedAt()` states. A model that extends `View` inherits it and gets instances of its own class back
- Added `Bucket::period()` for drilling from a bucket into a `count()`
- Added the `Exceptions\EloquentViewableException` marker interface, implemented by every exception the package throws
- Added the `querying.max_intervals` config option, defaulting to `10000`
- Added the `models.view.class` config option, the model class used for views. It defaults to `Models\View` and must name a class that extends it, otherwise `Support\Config` throws `InvalidConfiguration`
- Added `Support\Config`, typed access to the config file for internal use.
- Added `Querying\Exceptions\InvalidInterval` and `Querying\Exceptions\UnsupportedDriver`
- Added `Exceptions\InvalidConfiguration`, thrown by `Support\Config` when `querying.max_intervals` or `visitor.cookie.lifetime` is not a positive integer or when `querying.cache.key`, `cooldown.key` or `visitor.cookie.name` is empty, when `models.view.table_name`, `models.view.connection`, `recording.queue.connection`, `recording.queue.queue` or `querying.cache.store` is neither a string nor `null`, or when `recording.ignored_ip_addresses` holds something other than strings
- Added `Exceptions\InvalidViewable`, thrown by the `views()` helper for a class name that does not implement `Contracts\Viewable`, when a viewable's key is cast to something other than an integer or a string, and when `Views` counts, records or destroys views before `forViewable()` was called. It extends `InvalidArgumentException`, so existing `catch` blocks keep working
- Added the `Recording\Contracts\ViewStore` contract with `store()`, `storeMany()` and `forget()`, and `Recording\Stores\DatabaseStore` as the default implementation. The record action, `Views::destroy()` and the observer all write through it. `storeMany()` lands a batch of records in one insert statement
- Added the `recording.store.driver` config option and `Recording\Stores\StoreManager`, which builds the store it names. The `database` and `null` drivers ship; add one with `StoreManager::extend()`. An unregistered name throws `InvalidConfiguration`
- Added `Recording\Stores\NullStore`, a store that discards every view
- Added `ViewRecord::toPayload()` and `ViewRecord::fromPayload()` for stores that keep records as flat scalars

### Changed

- The `create_views_table` migration stub now also creates a composite `(viewable_type, viewable_id, viewed_at)` index named `views_viewable_viewed_at_index`; existing installations add it with the migration in the upgrade guide
- `Visitors\Visitor` now takes the auth factory as a fourth constructor argument, to read the signed-in model for `viewer()`
- `Recording\Recorder` resolves the viewer before the guards run and hands them the attempt with `viewer` set, and takes `Visitors\VisitorIdentity` as a sixth constructor argument. `Recording\Guards\EnforceCooldown` takes it as a second argument and keys the cooldown on the resolved visitor id
- `Views::fake()` honours `viewedBy()` when counting and keeps the viewer and the context on the recorded `ViewRecord`
- `Period` is now half-open: the start is included and the end is excluded, so `Period::create($a, $b)` and `Period::upto($b)` no longer match a view recorded exactly at `$b`
- `Period` now converts its bounds to the application timezone in its constructor, so bounds built in another timezone match the stored `viewed_at` wall clock and the getters return that zone
- `CacheKey` moved to `Querying\Cache\CacheKey`, and `CacheKey::make()` now takes a `ViewsQuery` and an optional `Granularity` instead of three loose parameters, and the digest changed and now includes the `querying.source.driver` name, so cached counts from earlier versions are recalculated once and switching the source driver starts fresh entries
- `Views::count()` and `Views::countByInterval()` now delegate to the bound `Querying\Contracts\ViewSource` instead of building the query themselves
- `InteractsWithViews::scopeWithViewsCount()` and `scopeOrderByViews()` now read through the bound `ViewSource` as a correlated subselect instead of `withAggregate()` on the `views` relation (no change in results)
- Crawler, Do Not Track, IP address and cooldown checks moved out of `Views` into guard classes listed under the `recording.guards` config key. A guard runs when it is listed and not otherwise. The default list keeps the v8 behaviour: crawlers and `ignored_ip_addresses` are dropped and Do Not Track is not honoured
- The config file is grouped by module: `store`, `guards`, `ignored_ip_addresses` and `queue` live under `recording`, `source`, `cache` and `max_intervals` under `querying`, and `visitor_cookie_key` is `visitor.cookie.name`. `models` is unchanged and `cooldown` gained `store` and `cache.store`. See the upgrade guide for the table
- `Crawlers\Contracts\CrawlerDetector::isCrawler()` now takes the user agent to judge, `isCrawler(?string $userAgent): bool`, and a `null` or empty user agent is never a crawler. The `IgnoreCrawlers` guard calls it with the visitor's user agent instead of asking the visitor
- The shipped crawler detector is a stateless singleton. It no longer captures the headers of the first request it sees, which in a long-running worker meant every later request was judged by that user agent
- The `Visitor` constructor now takes `Support\Config` instead of the config repository and no longer takes a `CrawlerDetector` (breaking only for classes that extend it and override the constructor)
- `CooldownManager` is now a driver manager that builds a `CooldownStore`, and `EnforceCooldown` depends on that contract. Cooldowns are keyed by visitor id, and the session store keeps them in a new format, so cooldowns running at upgrade end early once
- The test suite now runs against SQLite, MySQL, MariaDB and Postgres in CI (development only; no impact on consumers)
- Added a phpbench benchmark suite under `benchmarks/`, with a seeded dataset of up to fifty million views, `make bench-*` targets for every supported driver, a query plan report of every read benchmark as text or JSON, and a JSON description of the seeded dataset (development only; no impact on consumers)
- Moved classes into module namespaces, see the upgrade guide for the full table. `View` is now `Models\View`, the facade is `Facades\Views`, `InteractsWithViews` is `Concerns\InteractsWithViews`, `Visitor` and its contract are under `Visitors\`, `CrawlerDetector` and `CrawlerDetectAdapter` under `Crawlers\`, `CooldownManager` under `Cooldowns\`, and `ViewRecorded` and the recording job and action under `Recording\`
- Renamed the `Actions\CreateView` action to `Recording\Actions\RecordView`, its `Contracts\CreateView` contract to `Recording\Contracts\RecordsViews`, the `Jobs\StoreView` job to `Recording\Jobs\RecordViewJob` and the `Exceptions\ViewRecordException` exception to `Recording\Exceptions\RecordingFailed`
- The migration stub is published from `database/migrations/` instead of `migrations/` (no change for installations that already published it)
- Views are now written through the query builder instead of `Models\View::create()`, so the `creating` and `created` model events are no longer fired when a view is stored. Listen for `Recording\Events\ViewRecorded` instead
- `Models\View` now prefers its own `$table` and `$connection` properties over `models.view.table_name` and `models.view.connection`, which supply the defaults
- `ViewableObserver` is now `Recording\Observers\ViewableObserver` and deletes through the `ViewStore` instead of the `Views` builder (breaking only for code that references the class)
- Renamed `Recording\PendingView` to `Data\ViewRecord`
- `Recording\Contracts\RecordsViews::handle()` and `Recording\Contracts\ViewStore::store()` return `void` instead of the stored `Models\View`
- `Recording\Events\ViewRecorded` carries the `Data\ViewRecord` as `$record` instead of the `Models\View` model as `$view`, and no longer uses `SerializesModels`
- The `Views` constructor now takes the visitor, `Recording\Recorder`, `Querying\Reader` and `Recording\Contracts\ViewStore`, and nothing else (breaking only for classes that extend `Views` and override the constructor)

### Removed

- Removed the `$removeViewsOnDelete` model property in favour of `shouldRemoveViewsOnDelete()`. Declared `protected` as the v8 README showed, the property was ignored and the views were deleted anyway
- Removed `Visitors\Contracts\Visitor::isCrawler()`. The visitor reports its user agent and the `IgnoreCrawlers` guard asks the detector
- Removed the `ignore_bots` and `honor_dnt` config keys. Presence in `recording.guards` is the only switch for a guard
- Removed the `visitor_cookie_key` config key in favour of `visitor.cookie.name`
- Removed `CooldownManager::push()` in favour of the `CooldownStore` contract
- Removed the `Contracts\View` and `Contracts\Views` interfaces. A custom view model extends `Models\View` and is named in `models.view.class`; the `Views` builder is used by its class and is no longer replaceable through the container

### Fixed

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
