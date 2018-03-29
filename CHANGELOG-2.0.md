# Release Notes for 2.x.x

All notable changes to `eloquent viewable` will be documented in this file.

The format is based on [Keep a Changelog](http://keepachangelog.com/en/1.0.0/)
and this project adheres to [Semantic Versioning](http://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added

- Added `ViewsCountCacheRepository` class to `src/Cache` directory ([6868208](https://github.com/cyrildewit/eloquent-viewable/commit/377098a2cd408ca2a7a010d4f88a36f6438e6a7d))
- Added `ViewableService` contract to `src/Contracts/Services` directory ([8f09dd8](https://github.com/cyrildewit/eloquent-viewable/commit/e1049699e8ac5ecce708c2f05684fef8c43e495b))
- Added `Viewable` contract to `src/Contracts/Traits` directory ([e104969](https://github.com/cyrildewit/eloquent-viewable/commit/dd20dfaf8028a572995ffbff0697b51b68f4e10c))
- Added `View` model contract to `src/Contracts/Models` directory ([a3755be](https://github.com/cyrildewit/eloquent-viewable/commit/db70f1cd176ff8393442c30aa0b1096c135288f5))
- Added `EloquentViewableServiceProvider` class to `src/` directory ([843f544](https://github.com/cyrildewit/eloquent-viewable/commit/6868208ae0aa4c88acc35efbafb0648bf25e5f8d))
- Added `PastType` enum class to `src/Enums` directory ([8a238f8](https://github.com/cyrildewit/eloquent-viewable/commit/843f54451ed8782bf85bb911bf260010ea1b2f1b))
- Added `ProcessView` job class to `src/Jobs` directory ([a3755be](https://github.com/cyrildewit/eloquent-viewable/commit/db70f1cd176ff8393442c30aa0b1096c135288f5), [597df68](https://github.com/cyrildewit/eloquent-viewable/commit/cec4d4faf746cfb7a5b371550f07db9c497c4d64))
- Added `View` model class to `src/Models` directory ([a3755be](https://github.com/cyrildewit/eloquent-viewable/commit/db70f1cd176ff8393442c30aa0b1096c135288f5))
- Added `ViewableObserver` class to `src/Observers` directory ([4038ef8](https://github.com/cyrildewit/eloquent-viewable/commit/64d03fa288637a4068c2e27a55829362ad45c2ad))
- Added `ViewableService` class to `src/Services` directory ([cfbb84a](https://github.com/cyrildewit/eloquent-viewable/commit/ca3769d811dea857c2942116091007193ace34b1))
- Added `Viewable` trait to `src/Traits` directory ([d0c9ced](https://github.com/cyrildewit/eloquent-viewable/commit/cfbb84aeabe4a420cc129e4f342753295185fbd4), [10ebef5](https://github.com/cyrildewit/eloquent-viewable/commit/486af68e004fd7d52e7aa0292878324f0589f04c))
- Added `eloquent-viewable.php` config file to `resources/config` directory ([a44ddda](https://github.com/cyrildewit/eloquent-viewable/commit/a3755bed68dadca6de94157073b326a03fde9821))
- Added `create_views_table.php.stub` stub file to `resources/database/migrations` directory ([db70f1c](https://github.com/cyrildewit/eloquent-viewable/commit/f420b1a125f029ac104b6eb4d9afb95665dd579c))
- Added `Post` model class to `tests/Stubs/Models` directory ([97e3ddf](https://github.com/cyrildewit/eloquent-viewable/commit/26f58967146f86e5673707259910fae17ee4daae))
- Added `PostFactory` class to `tests/database/factories` directory ([97e3ddf](https://github.com/cyrildewit/eloquent-viewable/commit/26f58967146f86e5673707259910fae17ee4daae))
- Added `TestHelper` class to `tests` directory ([486af68](https://github.com/cyrildewit/eloquent-viewable/commit/d9e0f97132facbf4f7b62c884cc0dd157fad54bb))
- Added `ViewsCountCacheRepositoryTest` class to `tests/Unit/Cache` directory ([62c0f4b](https://github.com/cyrildewit/eloquent-viewable/commit/8b5d0a3a81789eef02a7be72a434f3f8bc6f58e1), [](https://github.com/cyrildewit/eloquent-viewable/commit/))
- Added `ViewableServiceTest` class to `tests/Unit/Services` directory ([62c0f4b](https://github.com/cyrildewit/eloquent-viewable/commit/8b5d0a3a81789eef02a7be72a434f3f8bc6f58e1), [](https://github.com/cyrildewit/eloquent-viewable/commit/))
- Added `ViewableTest` class to `tests/Unit/Traits` directory ([62c0f4b](https://github.com/cyrildewit/eloquent-viewable/commit/8b5d0a3a81789eef02a7be72a434f3f8bc6f58e1), [](https://github.com/cyrildewit/eloquent-viewable/commit/))
- Added `ViewableTest` class to `tests/Unit/Traits` directory ([62c0f4b](https://github.com/cyrildewit/eloquent-viewable/commit/8b5d0a3a81789eef02a7be72a434f3f8bc6f58e1), [](https://github.com/cyrildewit/eloquent-viewable/commit/))
- Added `2018_02_22_194715_create_posts_table.php` migration file to `tests/database/migrations` directory ([26f5896](https://github.com/cyrildewit/eloquent-viewable/commit/10ebef5f7b170c23637bd5a3c4e005beb3cbf321))

### Changed

- Changed the package name from `cyrildewit/laravel-page-view-counter` to `cyrildewit/eloquent-viewable` ([8a238f8](https://github.com/cyrildewit/eloquent-viewable/commit/8a238f8c8d637d7c3bb53dd692e7dd1b3605bd66))
- Require PHP 7+ ([8a238f8](https://github.com/cyrildewit/eloquent-viewable/commit/8a238f8c8d637d7c3bb53dd692e7dd1b3605bd66))
- Changed the license from MIT to Apache 2.0 ([c3584c9]
- Changed the `TestCase` abstract class in `tests` directory ([a241463](https://github.com/cyrildewit/eloquent-viewable/commit/97e3ddf9b7577a6cf79f8414a17ee1ede5c71f75))

### Removed

- Removed `SessionHistory` class from `src/PageViewCounter/Helpers` directory ([a798938](https://github.com/cyrildewit/eloquent-viewable/commit/504d48416b8900f3ce782a547c2dc83929859878))
- Removed `DateTransformer` class from `src/PageViewCounter/Helpers` directory ([a798938](https://github.com/cyrildewit/eloquent-viewable/commit/504d48416b8900f3ce782a547c2dc83929859878))
- Removed `PageView` model contract from `src/PageViewCounter/Contract` directory ([a798938](https://github.com/cyrildewit/eloquent-viewable/commit/504d48416b8900f3ce782a547c2dc83929859878))
- Removed `PageView` model from `src/PageViewCounter/Models` directory ([a798938](https://github.com/cyrildewit/eloquent-viewable/commit/504d48416b8900f3ce782a547c2dc83929859878))
- Removed `HasPageViewCounter` trait from `src/PageViewCounter/Traits` directory ([aa967f7](https://github.com/cyrildewit/eloquent-viewable/commit/a7989383c847803b663af9cddded86d829a28ab7))
- Removed `PageViewCounterServiceProvider` class from `src/PageViewCounter` directory ([aa967f7](https://github.com/cyrildewit/eloquent-viewable/commit/aa967f7517106714ded29cc0770a52e7cb6ff97f))
- Removed `create_page_views_table.php.stub` migration stub from `database/migrations` directory ([aa967f7](https://github.com/cyrildewit/eloquent-viewable/commit/aa967f7517106714ded29cc0770a52e7cb6ff97f))
- Removed `page-view-counter.php` config file from `src/config` directory ([f420b1a](https://github.com/cyrildewit/eloquent-viewable/commit/d0c9ced434467ae59d2476c2cfa31d55647a4626))
- Removed `Task` model from `tests/Models` directory ([504d484](https://github.com/cyrildewit/eloquent-viewable/commit/a44ddda5d597bb1d0fe57a154efae988a514a7f9))
- Removed `TestCase` class from `tests` directory ([504d484](https://github.com/cyrildewit/eloquent-viewable/commit/a44ddda5d597bb1d0fe57a154efae988a514a7f9))
- Removed `ViewViewableTest` class from `tests/TestCases` directory ([504d484](https://github.com/cyrildewit/eloquent-viewable/commit/a44ddda5d597bb1d0fe57a154efae988a514a7f9))

[Unreleased]: https://github.com/cyrildewit/eloquent-viewable/compare/v1.0.5...2.0
