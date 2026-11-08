---
title: "Testing"
description: "Views::fake() swaps storage for an in-memory fake, so a test records views without a views table and reads them back through the same views() calls."
sidebar:
  order: 0
---

`Views::fake()` swaps storage for an in-memory fake, so a test records views without a `views` table and reads them
back through the same `views()` calls:

```php
use CyrildeWit\EloquentViewable\Facades\Views;
use CyrildeWit\EloquentViewable\Data\ViewRecord;

it('records a view of the post', function (): void {
    $fake = Views::fake();

    $this->get(route('posts.show', $post));

    $fake->assertRecorded($post);
    $fake->assertRecorded($post, 1);
    $fake->assertRecorded($post, fn (ViewRecord $record): bool => $record->collection === 'sidebar');
    $fake->assertNotRecorded($otherPost);
    $fake->assertNothingRecorded();
    $fake->assertForgotten($post);
});
```

It stands in for [presence](/eloquent-viewable/rankings/presence/) too, with `presence.enabled` on. Put visitors on a model to test a
page with a live count, and assert who was kept active:

```php
$fake->present($product, 12);              // 12 visitors looking right now
$fake->present($product, 3, 'checkout');   // in one collection

$fake->assertPresent($product);
$fake->assertNotPresent($otherProduct);
$fake->assertLeft($product);
```

The guards still run. Every count reads from the fake, but the scopes, `withViewsCount()`, `orderByViews()`,
`whereViewsCount()` and the `whereViewedBy()` family, need SQL and throw `Querying\Exceptions\UnsupportedBySource`.

For tests and seeders that need real rows, the `View` model ships a factory:

```php
use CyrildeWit\EloquentViewable\Models\View;

View::factory()->for($post, 'viewable')->count(3)->create();
View::factory()->for($post, 'viewable')->fromVisitor('visitor_one')->inCollection('sidebar')->create();
View::factory()->for($post, 'viewable')->viewedAt(now()->subDays(2))->by($user)->create();
```
