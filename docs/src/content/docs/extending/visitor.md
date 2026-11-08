---
title: "Custom visitor information"
description: "The Visitor class reads the visitor's id, IP address, user agent and privacy signals from the request. Provide your own, for example on an API."
sidebar:
  order: 1
---

The `Visitor` class reads the current visitor from the request: a unique id from a cookie, the signed-in model, the IP
address, the user agent, and the Do Not Track and Global Privacy Control signals. On a RESTful API without that request
information, provide your own by implementing `CyrildeWit\EloquentViewable\Visitors\Contracts\Visitor` or extending the
default class. Return `null` for a user agent you do not have; it is never treated as a crawler.

Bind it globally in a service provider, or pass it for one call:

```php
use App\Services\Views\Visitor;
use CyrildeWit\EloquentViewable\Visitors\Contracts\Visitor as VisitorContract;

$this->app->bind(VisitorContract::class, Visitor::class);

views($post)->useVisitor(new Visitor())->record();
```
