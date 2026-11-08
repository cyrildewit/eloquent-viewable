---
title: "Bucket grammars"
description: "Teach countByInterval() to group views per hour, day, week, month or year on a database driver the package doesn't support yet, such as SQL Server."
sidebar:
  order: 9
---

`countByInterval()` groups in SQL, which differs per database. For a driver other than SQLite, MySQL, MariaDB or
Postgres, implement `BucketGrammar` and register it. The `Querying\Grammars\Concerns\ConvertsByOffset` trait handles
timezones, so a new grammar only implements `truncate()` and `shift()`.

```php
use CyrildeWit\EloquentViewable\Querying\Grammars\GrammarRegistry;

$this->app->afterResolving(GrammarRegistry::class, function (GrammarRegistry $grammars): void {
    $grammars->register('sqlsrv', \App\Grammars\SqlServerBucketGrammar::class);
});
```
