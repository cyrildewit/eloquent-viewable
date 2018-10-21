# Release Notes

All notable changes to `Eloquent Viewable` will be documented in this file.

The format is based on [Keep a Changelog](http://keepachangelog.com/en/1.0.0/)
and this project adheres to [Semantic Versioning](http://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [v2.4.2] (2018-10-21)

### Fixed

- ProcessView job is always failing

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

[Unreleased]: https://github.com/cyrildewit/eloquent-viewable/compare/v2.4.0...HEAD
[v2.4.0]: https://github.com/cyrildewit/eloquent-viewable/compare/v2.3.0...v2.4.0
[v2.3.0]: https://github.com/cyrildewit/eloquent-viewable/compare/v2.2.0...v2.3.0
[v2.2.0]: https://github.com/cyrildewit/eloquent-viewable/compare/v2.1.0...v2.2.0
[v2.1.0]: https://github.com/cyrildewit/eloquent-viewable/compare/v2.0.0...v2.1.0
[v2.0.0]: https://github.com/cyrildewit/eloquent-viewable/compare/v1.0.5...v2.0.0
