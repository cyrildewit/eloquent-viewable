---
title: "Collections"
description: "Store different kinds of views of the same model in their own collection, and count them the same way."
sidebar:
  order: 5
---

Store different kinds of views of the same model in their own collection, and count them the same way:

```php
views($post)->collection('customCollection')->record();
views($post)->collection('customCollection')->count();
```
