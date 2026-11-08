---
title: "Requirements"
description: "The Laravel, PHP and database versions each Eloquent Viewable release supports, and what MySQL, MariaDB, PostgreSQL and SQLite need."
sidebar:
  order: 1
---

| Package Version                                                            | Laravel    | PHP  |
|----------------------------------------------------------------------------|------------|------|
| [9.x](https://packagist.org/packages/cyrildewit/eloquent-viewable#9.x-dev) | 13.x       | 8.5+ |
| [8.x](https://packagist.org/packages/cyrildewit/eloquent-viewable#8.x-dev) | 13.x       | 8.5+ |
| [7.x](https://packagist.org/packages/cyrildewit/eloquent-viewable#7.x-dev) | 6.x – 13.x | 7.4+ |

On MySQL the package needs version 8.0 or newer, for the window function the rollups of a [dimension](/eloquent-viewable/viewers/dimensions/) rank
their values with. MariaDB, Postgres and SQLite have one on every version Laravel supports.

The package supports [Laravel Octane](https://laravel.com/docs/octane). Nothing about one request, such as its
visitor, viewer, cooldowns or config, carries over to the next request a worker handles.
