# Changelog

All notable changes to `laravel-page-views-counter` will be documented in this file.

## [Unreleased]

## [1.0.4] - 20-02-2018

### Fixed

- Use the `Request` facade for getting the ip address of the visitor instead of creating a new empty request (#39)

## [1.0.3] - 12-01-2018

### Fixed

- Use `->getKey()` method to get the models primary key value, instead of using the static attribute `id` #35.

## [1.0.2] - 02-01-2018

### Fixed

- Fix where clauses in `retrievePageViews()` method in `HasPageViewCounter` because they were overriding each other.

## [1.0.1] - 28-12-2017

## Changed

- Removed the protected `transformDate()` method from the `HasPageViewCounter` trait and put it into his own helper class: `DateTransformer`.

## [1.0.0] - 24-12-2017

### Added

- Added the `UniquePageViewsBefore()` method to the `HasPageViewCounter` trait.
- `ViewViewableTest.php`

### Changed

- Replaced `fromCamelCaseToDashes()` method in `SessionHistory` with the provided `snake_case()` method of Laravel.
- Updated the tests.

### Removed

- `VisitVisitableTest.php`

## [1.0.0-alpha] - 23-12-2017

A complete new version of this package.

### Added

- New trait: `HasPageViewCounter.php`
- New service provider: `PageViewCounterServiceProvider.php`
- New SessionHistory helper.

### Changed

- Package name: `PageVisitsCounter` to `PageViewCounter`.
- PHP namespace: `Cyrildewit\PageVisitsCounter` -> `CyrildeWit\PageViewCounter`.
- Config file name: `page-visits-counter.php` -> `page-view-counter.php`.
- PageView model contract: `PageVisit.php` -> `PageView.php`
- Displaced SessionHistory to `Helpers/`

### Removed

- Trait: `HasPageVisitsCounter.php`.
- Service provider: `PageVisitsCounterServiceProvider.php`.
- `SessionHistory` class.

## [0.1.7] - 10-10-2017

### Added

- The Eloquent model will now pass the fillable attributes

### Changed

- Improved spelling and grammar in `README.md`
- Updated the e-mailaddress in the `README.md` file.

## [0.1.6] - 19-7-2017

### Added

- The `composer.json` now consists of package auto discovery.

### Changed

- The DocBlocks of some PHP classes have been rewritten to be more descriptive and cleaner.
- Updated the copyright notices inside the MIT License file.
- The badges inside the `README.md` file have been updated. They all now now have a similar style.
- The description inside the config file has been improved.
- Updated the documentation inside the `README.md` file.
- Replaced the double quotes inside the `VisitVisitableTest.php` file to single quotes.

### Removed

- Unnecessary comments have been removed from the SessionHistory class.

## [0.1.5] - 14-7-2017

### Changed

- Improved `README.md`.
- Updated the `CHANGELOG.md` file.
- Improved the comments inside the following files: `PageVisitsCounterServiceProvider.php`, `HasPageVisitsCounter.php` and `SessionHistory.php`.

## Removed

- Removing `string` and `int` type hints from the code.

## [0.1.4] - 17-6-2017

### Added

- A the MIT `LICENSE` file.
- A default value for retrieving the table name of `page-visits` from the config file.

### Changed

- Improved `README.md`.
- Changed from phpunit 6.2.0 to 5.7.0 because Orchestral Testbench isn't compatible.
- Changed from protected to public method: `fromCamelCaseToDashes( string $value )` inside the `SessionHistory` class.
- Updated the TestCase `VisitVisitableTest`.

### Removed

- The php file extension from the mergeConfigFrom() function inside the `PageVisitsCounterServiceProvider`.
- The php file extension from the config() function inside the `HasPageVisitsCounter`.

## [0.1.3] - 16-6-2017

### Added

- The `.travis.yml` file to integrate TravisCI into this project.

### Changed

- Fixed a forgotten colon in `README.md`.

## [0.1.2] - 15-6-2017

### Added

- New configurable settings to the config file. Developers can now change the primary session key that is used to store the visit history of the users.
- Added the `SessionHistory` class for providing an elegant way of checking and adding new visits into the session of the user.
- Integrated the SessionHistory functionality inside the `HasPageVisitsCounter` trait. Added two methods: `addVisitThatExpiresAt( Carbon $expires_at )` and `addVisitThatExpiresAtAndCountAll()`.
- The `tests/Models/Task` model for using inside the TestCases.
- The main TestCase base class (`tests/TestCase.php`).
- The first TestCase: `VisitVisitableTest` with one method `it_can_store_new_visits_into_the_database()` and.

### Changed

- Improved the the documentation and project details inside `README.md`. Updated old code examples.
- The directory where phpunit will search for tests inside `phpunit.xml`. From `tests` to `tests/TestCases`.
- The namespace from `CyrildeWit/..` to `Cyrildewit/..` inside `Models/PageVisit.php` and `Traits/HasPageVisitsCounter.php`.

## [0.1.1] - 13-6-2017

### Changed

- Improved the `README.md` file and updated the code examples.
- Renamed the function from `retrievePageVisitsFrom` to `retrievePageVisitsCountFrom` inside the `HasPageVisitsCounter` trait.
- Improved the `convertNumber` function inside the `HasPageVisitsCounter` trait. It now returns an object with two properties: number and formatted.

## 0.1.0 - 12-6-2017

The initial release of the Laravel Page Visits Counter package.

### Added

- A config file that can be published to the users Laravel application.
- A database migration file that can be published to the users Laravel application.
- A Laravel service provider for integrating this package into Laravel applications.
- An README.md with project details and documentation.
- This CHANGELOG.md file.
- A .styleci.yml file with settings.
- The files: `.gitattributes`, `.gitignore`, `composer.json` and `phpunit.xml`.
- A trait (`HasPageVisitsCounter`) that can be added to Eloquent models.
- A default Eloquent model (`PageVisit`) for storing the page visits into the database.
- A contract for the `PageVisit` Eloquent model because it can be changed within the configuration file. It uses Laravels Service Container to updates this automatically.

[Unreleased]: https://github.com/cyrildewit/laravel-page-visits-counter/compare/v1.0.4...HEAD
[1.0.4]: https://github.com/cyrildewit/laravel-page-visits-counter/compare/v1.0.3...v1.0.4
[1.0.3]: https://github.com/cyrildewit/laravel-page-visits-counter/compare/v1.0.2...v1.0.3
[1.0.2]: https://github.com/cyrildewit/laravel-page-visits-counter/compare/v1.0.1...v1.0.2
[1.0.1]: https://github.com/cyrildewit/laravel-page-visits-counter/compare/v1.0.0...v1.0.1
[1.0.0]: https://github.com/cyrildewit/laravel-page-visits-counter/compare/v1.0.0-alpha...v1.0.0
[1.0.0-alpha]: https://github.com/cyrildewit/laravel-page-visits-counter/compare/v0.1.7...v1.0.0-alpha
[0.1.7]: https://github.com/cyrildewit/laravel-page-visits-counter/compare/v0.1.6...v0.1.7
[0.1.6]: https://github.com/cyrildewit/laravel-page-visits-counter/compare/v0.1.5...v0.1.6
[0.1.5]: https://github.com/cyrildewit/laravel-page-visits-counter/compare/v0.1.4...v0.1.5
[0.1.4]: https://github.com/cyrildewit/laravel-page-visits-counter/compare/v0.1.3...v0.1.4
[0.1.3]: https://github.com/cyrildewit/laravel-page-visits-counter/compare/v0.1.2...v0.1.3
[0.1.2]: https://github.com/cyrildewit/laravel-page-visits-counter/compare/0.1.1...v0.1.2
[0.1.1]: https://github.com/cyrildewit/laravel-page-visits-counter/compare/0.1.0...0.1.1
