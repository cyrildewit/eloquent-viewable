# Release Notes

All notable changes to `Eloquent Viewable` will be documented in this file.

The format is based on [Keep a Changelog](http://keepachangelog.com/en/1.0.0/)
and this project adheres to [Semantic Versioning](http://semver.org/spec/v2.0.0.html).

## Unreleased

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
- Changed the `Views` class constructor arguments. Added the `Visitor` as first argument and removed `VisitorCookieRepository $visitorCookieRepository`, `rawlerDetector $crawlerDetector` and `IpAddressResolver $ipAddressResolver`.
- Replaced calls to `$this->crawlerDetector` in `Views` with new `Visitor` class implementation.
- Replaced calls to `requestHasDoNotTrackHeader` in `Views` with new `Visitor` class implementation.
- Changed type of primary key from `increments` to `bigIncrements` in `views` table migration.
- Bumped minimum requirements for Laravel framework components to `^6.0|^7.0`.
- Removed check for provided viewable type in `getConnectionName()`, `getDatabaseName()`, `getModelSlug()` and `getKeySlug` in `CacheKey::class`.
- Renamed `ViewSessionHistory` class to `CooldownMananger`.
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
- Removed the `VisitorCookieRepository` (logic is move to the `Visitor` class).

## [v4.1.1] (2019-10-18)

### Fixed

- Update required dependencies in composer.json to adhere to new Laravel 6 verison scheme

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
- Added `collection` column to views table shema
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

## [Unreleased]

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

- Added `visitor` collumn to the  `create_views_table` migration stub

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

[Unreleased]: https://github.com/cyrildewit/eloquent-viewable/compare/v6.0.0...HEAD
[v6.0.0]: https://github.com/cyrildewit/eloquent-viewable/compare/v5.2.0...v6.0.0
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
