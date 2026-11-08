---
title: "Crawler detector"
description: "The IgnoreCrawlers guard asks the bound CrawlerDetector whether a user agent belongs to a crawler."
sidebar:
  order: 10
---

The `IgnoreCrawlers` guard asks the bound `CrawlerDetector` whether a user agent belongs to a crawler. The shipped
detector wraps [CrawlerDetect](https://github.com/JayBizzle/Crawler-Detect).

```php
use CyrildeWit\EloquentViewable\Crawlers\Contracts\CrawlerDetector;

final class ListedCrawlerDetector implements CrawlerDetector
{
    public function isCrawler(?string $userAgent): bool
    {
        return $userAgent !== null && preg_match('/bot|crawler|spider/i', $userAgent) === 1;
    }
}

$this->app->singleton(CrawlerDetector::class, ListedCrawlerDetector::class);
```
