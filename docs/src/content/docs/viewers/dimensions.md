---
title: "Dimensions"
description: "Count views by source, medium, campaign, device, country or a value of your own, each stored in a column of its own so a breakdown is one query."
sidebar:
  order: 4
---

Dimensions count views by where they came from and who saw them: the source, the medium, the campaign, the device,
the country, or a value of your own. Each one turns the request into one short value when a view is recorded, kept in
a column of its own, so a count by it is one grouped query.

## Turning one on

Every dimension is off until you list it. The published config lists the built-in ones commented out:

```php
// config/eloquent-viewable.php
'dimensions' => [
    'definitions' => [
        'source' => CyrildeWit\EloquentViewable\Dimensions\Source::class,
        // 'medium' => CyrildeWit\EloquentViewable\Dimensions\Medium::class,
    ],
],
```

Each dimension is kept in a column named after it, so add the column next:

```bash
php artisan views:dimensions   # writes a migration that adds the columns the views table lacks
php artisan migrate
```

Views recorded from then on carry a source, and you can count by it:

```php
views($post)->countBy('source')->all(); // ['Google' => 120, 'Direct' => 64, 'Hacker News' => 31]
```

Views recorded before have no value, which `countBy()` counts in `none()`. `views:doctor` fails when a dimension in
config has no column.

## The built-in dimensions

| Dimension      | Values                                                     | Read from                                                                                                                  |
|----------------|------------------------------------------------------------|----------------------------------------------------------------------------------------------------------------------------|
| `Source`       | `Google`, `Hacker News`, `Direct`, `newsletter`            | `utm_source` or `ref`, then the name the source list gives the referring host, then the bare host. `Direct` without either |
| `Medium`       | `organic`, `social`, `email`, `referral`, `cpc`, `direct`  | `utm_medium`, then the medium the source list gives the referring host                                                     |
| `Campaign`     | `spring-sale`                                              | `utm_campaign`, lowercased. Personal, see below                                                                            |
| `ReferrerHost` | `news.ycombinator.com`                                     | the referring host without `www.`, never its path or query string                                                          |
| `Device`       | `mobile`, `tablet`, `desktop`, `bot`                       | the user agent. `bot` comes from the crawler detector and only shows up when `IgnoreCrawlers` is off                       |
| `Country`      | `NL`, `US`                                                 | the `CF-IPCountry` header Cloudflare sets. The IP address is never stored                                                  |

A referrer from the application itself, the host of `app.url` or of the request, counts as no referrer. List other
hosts of your own under `dimensions.internal_hosts`.

The package ships a list of about 140 referring hosts: search engines, social networks, AI assistants, webmail and
aggregators. A host it does not know is counted by its own name. Add or override hosts, and `utm_source` spellings:

```php
'dimensions' => [
    'sources' => [
        'news.example.com' => ['Example News', 'referral'],
    ],
    'source_aliases' => [
        'nl' => 'Newsletter', // ?utm_source=nl counts as Newsletter
    ],
],
```

`Country` reads another header with `HeaderCountry`, such as the one CloudFront sets. Only trust a header your proxy
overwrites:

```php
'country' => [Country::class, 'resolver' => HeaderCountry::class, 'header' => 'CloudFront-Viewer-Country'],
```

Without a proxy that adds one, look the country up yourself, for example with MaxMind's free GeoLite2 database and
`composer require geoip2/geoip2`:

```php
use CyrildeWit\EloquentViewable\Dimensions\Contracts\CountryResolver;
use CyrildeWit\EloquentViewable\Dimensions\DimensionInput;
use GeoIp2\Database\Reader;
use GeoIp2\Exception\AddressNotFoundException;

final class MaxMindCountry implements CountryResolver
{
    public function __construct(private Reader $reader) {}

    public function country(DimensionInput $input): ?string
    {
        $ip = $input->visitor->ip();

        if ($ip === null) {
            return null;
        }

        try {
            return $this->reader->country($ip)->country->isoCode;
        } catch (AddressNotFoundException) {
            return null;
        }
    }
}
```

Bind the `Reader` to the path of your database in a service provider, and keep the database up to date with
MaxMind's `geoipupdate`.

## Counting by a dimension

`countBy()` returns a `DimensionCounts`, the views per value with the most viewed first:

```php
$sources = views($post)->period(Period::pastDays(30))->countBy('source', limit: 5);

$sources->all();            // ['Google' => 120, 'Direct' => 64, ...], the top five
$sources->get('Google');    // 120
$sources->share('Google');  // 0.462, of every view in the period
$sources->other();          // the views of the values past the top five
$sources->none();           // the views without a value
$sources->total();          // every view in the period
```

It works with `unique()`, `collection()`, `remember()` and on a whole type, `views(Post::class)->countBy('device')`.
With `unique()` every number counts distinct visitors, so they do not add up: a visitor who came once from Google and
once directly counts once for each and once in the total.

`whereDimension()` narrows any count, series or ranking to the views with a value, or one of several. Every call
narrows further:

```php
views($post)->whereDimension('device', 'mobile')->count();
views($post)->whereDimension('source', ['Google', 'Bing'])->countByInterval(Granularity::Day);
views(Post::class)->whereDimension('country', 'NL')->top(10);
views($post)->whereDimension('medium', 'email')->countBy('campaign');
```

The Eloquent scopes take the same filters as a map of each dimension to one value or a list of values, by name:

```php
Post::orderByViews(period: Period::pastDays(7), dimensions: ['source' => 'Google'])->paginate();
Post::withViewsCount(dimensions: ['device' => ['mobile', 'tablet']])->get();
Post::whereViewsCount('>', 100, dimensions: ['source' => 'Google', 'device' => 'mobile'])->get();
```

Every scope that counts views takes `dimensions`: `orderByViews()`, `orderByUniqueViews()`, `withViewsCount()`,
`whereViewsCount()`, `whereUniqueViewsCount()`, `orderByTrending()`, `withTrendingScore()`, the `whereViewedBy()`
family and `recommendedFor()`. A dimension that is not in config throws `Dimensions\Exceptions\UnknownDimension`.

## Writing your own

Extend `Dimensions\Dimension` and return the value for the view, or null:

```php
use CyrildeWit\EloquentViewable\Dimensions\Dimension;
use CyrildeWit\EloquentViewable\Dimensions\DimensionInput;

final class PlanDimension extends Dimension
{
    public function resolve(DimensionInput $input): ?string
    {
        return $input->visitor->viewer()?->plan;
    }
}
```

The input carries the `visitor`, the `viewable`, the `collection` and `context` of the view, the referring host in
`referrer`, and the query parameters of the page through `landing('utm_source')`. `header()` reads a request header
and `externalReferrer()` leaves out a referrer from the application itself.

A value is trimmed, stripped of control characters and cut at 64 characters before it is stored. A dimension that
throws is reported and leaves its value null, so one broken dimension never stops a view from being recorded.

Every dimension takes three options in config, without a class of its own:

```php
'plan' => [PlanDimension::class, 'personal' => true, 'maxValues' => 10, 'json' => 'context->plan'],
```

- **`personal`**: anonymising clears the value. Off by default, on for `Campaign`.
- **`maxValues`**: how many values a rollup bucket keeps, 20 by default. See below.
- **`json`**: keep the value at a path in `context` instead of a column, so it needs no migration. A JSON path cannot
  use a plain index, and `views:doctor` warns about one on a large table.

An option the class does not take throws `InvalidConfiguration` naming the entry. Test a dimension of your own without
a request through `DimensionInput::fake()`:

```php
expect(new PlanDimension()->resolve(DimensionInput::fake(viewer: $user)))->toBe('pro');
```

To move a dimension from `context` into a column, drop its `json` option, run `views:dimensions` and migrate, then copy
the values it already has:

```bash
php artisan views:dimensions --backfill=plan --max-seconds=300
```

It copies `context->plan`, or the path in `--from`, in chunks, and leaves views whose column has a value alone, so a
run stopped by `--max-seconds` carries on where it stopped.

## Dimensions in rollups

[Rollups](/eloquent-viewable/scaling/rollups/) keep a dimension's history once its views are pruned. List the dimensions to fold:

```php
'retention' => [
    'rollups' => [
        'tiers' => ['day' => '2y', 'month' => null],
        'dimensions' => ['source', 'device', 'country'],
    ],
],
```

Each gets a rollup of its own, `views:source`, with the tiers and groupings of the built-in rollup, and `countBy()` and
`whereDimension()` read it together with the views table. A dimension left out is only counted while its views are in
the views table.

The hour tier of a dimension splits every hour by every value, so it soon holds most of the rollup rows. Leave it out
of a dimension you do not read by the hour:

```php
'dimensions' => [
    'source',                                     // every tier
    'country' => ['tiers' => ['day', 'month']],   // no hour tier
],
```

The tiers must be listed in `retention.rollups.tiers` and are kept as long as they are there. A series by the hour of
that dimension, such as `whereDimension('country', 'NL')->countByInterval(Granularity::Hour)`, reads the views table,
and once its views are pruned it counts nothing. Trending, rising and spike baselines read the hourly totals of the
built-in rollup, so they are not affected. The next `views:maintain` drops the hour rows a dimension already has, and
`views:doctor` advises this once a dimension's hour tier passes 100,000 rows.

What to know:

- **Each bucket keeps a dimension's top values.** The rest is folded into `other()`, so a campaign with an id per link
  cannot flood the rollups table. Raise or lift the cap with `maxValues`, `null` keeps every value.
- **Per-value counts from rollups are lower bounds.** The top values are picked per bucket, so a value in the top 20 on
  Monday but not on Tuesday counts Tuesday's views in `other()`. The total is exact. `other() > 0` says the split is
  not complete.
- **The unique visitors of `other` are exact per bucket.** They are counted from the views past the cap, not added up.
- **One dimension at a time.** A count that combines two dimensions, such as `whereDimension('source', 'Google')`
  with `countBy('device')`, or counts unique visitors across several values, reads the views table. Once views are
  pruned it throws `UnsupportedBySource`, or `ResolutionUnavailable` with `strict`, rather than count only what is
  left. Start its period after the pruned views, or count one dimension at a time.
- **A dimension added later has no history before its first fold.** It starts from the oldest view still in the
  table, and `views:doctor` says when that is later than the views the built-in rollup counts.
- **The cap needs MySQL 8.0 or newer**, for its window function. MariaDB, Postgres and SQLite have one on every
  version Laravel supports. `views:rollup --rollup=views:source` folds one dimension.

## Privacy

The package never stores the full referrer, the full user agent or the IP address: `Source` and `ReferrerHost` keep
the host, `Device` a word, `Country` a code. Anonymising clears the dimensions marked `personal`, and the default
`retention.anonymise.columns` includes `dimensions`. A personal dimension kept in `context` is cleared along with
`context`. `Erasure\Actions\ExportViewHistory` includes every dimension of a view.

A personal dimension can still be folded into rollups, when you know its values identify no one. Rollup rows carry no
visitor, so neither anonymising nor erasing a person reaches them, and `views:doctor` warns about each personal
dimension that is folded. To fold `Campaign`, give it `'personal' => false` only if your campaign names carry no ids.

## Testing with dimensions

`Views::fake()` keeps the values of every recorded view and answers `countBy()` and `whereDimension()` from them:

```php
$fake = Views::fake();

$this->get('/posts/1?utm_source=newsletter');

$fake->assertRecorded($post, fn (ViewRecord $view): bool => $view->dimensions['source'] === 'newsletter');
```

A dimension kept in `context` is in `$view->context`. Seed views with values through the factory:
`View::factory()->withDimensions(['source' => 'Google'])`.
