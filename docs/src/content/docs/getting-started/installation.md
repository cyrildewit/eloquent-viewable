---
title: "Installation"
description: "Install Eloquent Viewable in a Laravel application with Composer, then publish and run the migrations, and optionally publish the config file."
sidebar:
  order: 2
---

Install the package via Composer, publish the migrations and run them:

```bash
composer require cyrildewit/eloquent-viewable:^9
php artisan vendor:publish --provider="CyrildeWit\EloquentViewable\EloquentViewableServiceProvider" --tag="migrations"
php artisan migrate
```

This publishes six migrations, one per table: the views table and the tables that retention, rollups, pairs,
milestones and spikes use. Those features stay off until you configure them, so their tables stay empty until then. You
can delete the migration of a feature you will not use before running it. Publishing again adds only the migrations
whose files are missing.

Optionally publish the config file:

```bash
php artisan vendor:publish --provider="CyrildeWit\EloquentViewable\EloquentViewableServiceProvider" --tag="config"
```

Once it is set up, [`php artisan views:doctor`](/eloquent-viewable/scaling/checking-your-setup/) checks the setup and says what to fix.

## Models keyed by UUID or ULID

The migration follows your application's morph key type, so if you already call `Schema::morphUsingUuids()` or
`Schema::morphUsingUlids()` it needs no changes. Otherwise edit the published migration before running it:

```php
$table->uuidMorphs('viewable');         // or ulidMorphs('viewable')
$table->nullableUuidMorphs('viewer');   // or nullableUlidMorphs('viewer'), to match your user model
```

All viewable models share one `viewable_id` column, so they need the same key type.
