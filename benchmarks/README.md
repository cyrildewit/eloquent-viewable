# Benchmarks

Performance benchmarks for the package, built on [phpbench](https://phpbench.readthedocs.io/). They time the paths that
get expensive as the `views` table grows, against a seeded dataset of up to fifty million rows, on every supported
database. Results are stored, so a branch can be compared against `main` and a run fails when a query got slower.

Nothing here ships with the package or runs in CI. The numbers depend on the machine, so they are only comparable with
numbers from the same machine, the same driver and the same dataset.

## Quick start

```bash
make bench-seed                          # a million views on SQLite, a few seconds
make bench                               # run everything
make bench ARGS="--group=read"           # only the read queries
make bench-explain                       # the SQL and query plan of every read benchmark
```

Pick another database with `DRIVER`. The target starts the database service first, and the service keeps its data on
a named volume, so a dataset only has to be seeded once.

```bash
make bench-seed DRIVER=mysql SIZE=medium # ten million views on MySQL
make bench DRIVER=mysql
make bench DRIVER=pgsql ARGS="--filter=benchCountByInterval"
```

| Variable  | Values                                    | Default    |
|-----------|-------------------------------------------|------------|
| `DRIVER`  | `sqlite`, `mysql`, `mariadb`, `pgsql`     | `sqlite`   |
| `SIZE`    | `small`, `medium`, `large`                | `small`    |
| `TAG`     | letters, digits, `.` and `_`              | `baseline` |
| `INDEXES` | see [optional indexes](#optional-indexes) | `none`     |
| `ARGS`    | passed to phpbench or the script          |            |

## Comparing a branch

Store a run on the branch you compare against, switch, and compare. The second run prints both columns and the
difference, and fails when a subject's mode is more than 10% slower than the stored one.

```bash
git switch main
make bench-baseline TAG=main
git switch feature/my-change
make bench-compare TAG=main
```

The runs are stored under `build/benchmarks/storage`, which is not committed. A tag may contain letters, digits, dots
and underscores. Tags are kept per driver only by convention, so name them `main_mysql` and so on when you store runs
for several databases.

A few things to know when reading the numbers. Each subject runs several iterations in a fresh PHP process, and the
report shows the mode and the relative standard deviation. A deviation above 5% makes phpbench retry the subject. Run
the comparison with nothing else loading the machine, and run it twice when a difference looks surprising.

## The dataset

`make bench-seed` replaces whatever the connection holds with a deterministic dataset. The same size and seed produce
the same rows every time, on every machine.

| Size     | Views      | Articles | Videos | Seeding on SQLite |
|----------|------------|----------|--------|-------------------|
| `small`  | 1,000,000  | 1,000    | 100    | seconds           |
| `medium` | 10,000,000 | 10,000   | 1,000  | a minute          |
| `large`  | 50,000,000 | 50,000   | 5,000  | several minutes   |

The shape is meant to look like production rather than a uniform spray:

- Views spread over the articles on a power law, so a few articles are hot and most are cold. The hottest article in
  any size carries about a tenth of all views. The seeder records which article is hottest and which is coldest, and
  the benchmarks target those.
- Visitors come back on a heavy-tailed distribution, so a unique count is well below the plain count.
- Timestamps run chronologically over the two years before `2026-01-01`, with a daily curve, quieter weekends and a
  rising trend. The rows are inserted in that order, so the primary key orders them by time the way real traffic does.
- One in ten views belongs to a video, so every query has to filter on `viewable_type`.
- One in five views is in a named collection.
- Every view has a source and a device. The source comes from a short head, such as `Google` and `Direct`, and one view
  in twenty from a long tail of two hundred sites, so a capped rollup folds some of them away. The table also has the
  empty `medium`, `campaign` and `country` columns, so `DimensionsBench` can record with five dimensions.

Every view is recorded before `2026-01-01 00:00:00`. The benchmarks build their periods relative to that anchor, so
"the past 30 days" covers the same rows on every run, whenever it happens. The write benchmarks remove the rows they
add.

Pass `--seed` through `ARGS` to seed a different draw of the same shape:

```bash
make bench-seed SIZE=medium ARGS="--seed=7"
```

## Optional indexes

The README suggests three indexes for apps that need them, and the migration creates a fourth on `viewed_at` that the
seeder drops so the read benchmarks keep their plans. A seeded dataset starts without all four. `make bench-indexes` adds or drops them in place, which is far quicker than seeding again, and the same
benchmarks measure the difference.

| Name             | Index                                                                               | Serves                                       |
|------------------|-------------------------------------------------------------------------------------|----------------------------------------------|
| `visitor`        | `(viewable_type, viewable_id, viewed_at, visitor)`, `INCLUDE (visitor)` on Postgres | `unique()` counts without touching the table |
| `type-viewed-at` | `(viewable_type, viewed_at)`                                                        | counts over a whole type within a period     |
| `visitor-history` | `(visitor, viewed_at, viewable_type, viewable_id)`                                 | `alsoViewed()` pairing through the visitors  |
| `viewed-at`       | `(viewed_at)`, which the migration creates and the seeder drops                     | anonymising and pruning by date              |

```bash
make bench-baseline TAG=plain
make bench-indexes INDEXES=visitor,type-viewed-at
make bench-compare TAG=plain
make bench-indexes INDEXES=none
```

## Query plans

`make bench-explain` prints the SQL the package generates for every variant of the `read` group and the plan the
current driver chooses for it. The cases are discovered from the benchmark classes and named as in the phpbench
report, `CountViewsBench::benchCount (hot article, all time)`, so the report cannot drift from what `make bench`
times. Plans are deterministic where timings are noisy, so a query that stopped using the composite index shows up
here as a changed plan before it shows up as a slower number.

`ARGS=--analyze` executes the queries as well and shows the actual row counts and times, where the driver supports
it. `ARGS=--group=<name>` picks another phpbench group. `ARGS=--output=<file>` writes the same report as JSON next to
printing it: the driver, whether the queries were analyzed, the group, and per variant its class, subject, parameter
set name, parameters and every statement it ran with its plan. The results repository stores this file with every
run and shows the SQL on each benchmark's page.

The subjects run under `pretend()`, which returns no rows, so a subject whose later statements depend on the rows of
an earlier one, such as `recommended()`, only shows its first. `ARGS=--execute` runs each subject for real inside a
transaction that is rolled back, and prints every statement with the time it took. `ARGS=--filter=<text>` keeps the
variants whose name contains the text.

```bash
make bench-explain DRIVER=pgsql ARGS=--analyze
make bench-explain DRIVER=mysql ARGS="--output=build/queries.json"
make bench-explain ARGS="--execute --filter=RecommendedBench"
```

## Describing the dataset

`make bench-describe` prints the seeded dataset and the database it lives in as JSON: the size, the seed, the counts,
the hottest and coldest article, the optional indexes, the schema version, the driver and server version of the
connection, and the Laravel version installed. `ARGS=--output=<file>` writes a file instead, which is what scripts should use, since standard output also
carries Make's and Docker's own lines.

```bash
make bench-describe DRIVER=pgsql
make bench-describe DRIVER=mysql ARGS="--output=build/dataset.json"
```

## What is measured

Subjects are grouped so a run can pick a part. `make bench ARGS="--group=write"` runs one group,
`ARGS="--filter=benchOrderBy"` a few subjects.

| Group    | Benchmark                     | Measures                                                                                                                                                                                                                                                          |
|----------|-------------------------------|-------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------|
| `read`   | `AlsoViewedBench`             | `alsoViewed()`, the ten models the visitors of the hot and the cold article also viewed, reading their thousand most recent visitors and every visitor, over the same periods as `CountViewsBench` |
| `read`   | `CountViewsBench`             | `count()` and `unique()->count()`, for the hot article, the cold article and the whole type, over all time, a year, 30 days and a day                                                                                                                             |
| `read`   | `CountViewsByCollectionBench` | `countByCollection()`, plain and unique, for the same targets and periods as `CountViewsBench`, so the two show what the grouping adds                                                                                                                            |
| `read`   | `CountViewsByIntervalBench`   | `countByInterval()`, plain and unique, from a week of hours to two years of months, and the plain form again in Europe/Amsterdam, across up to four daylight saving transitions                                                                                   |
| `read`   | `CountViewsForViewablesBench` | `forViewables()->counts()` and its `unique()` form over the twenty hottest and the twenty coldest articles, against the loop of `count()` calls it replaces, over the same periods, and both again for 100, 250 and 1,000 articles, which span several statements |
| `read`   | `CountViewsInCollectionBench` | `collection()->count()` and its `unique()` form, for the same targets and periods as `CountViewsBench`, so the two show what the filter on a column outside the index adds                                                                                        |
| `read`   | `GrowthBench`                 | `rising()` across every type and within articles over the last hour and day before the anchor, `anomalies()` against the same window on 4 and 8 past weeks and 7 past days, its drops with a negative threshold, which run the same statement, and `againstBaseline()` for the hot and the cold article |
| `read`   | `OrderByViewsBench`           | `orderByViews()` and `orderByUniqueViews()`, first page of twenty, over the same periods                                                                                                                                                                          |
| `read`   | `RecommendedBench`            | `recommended()` and `recommendedFor()`, ten articles for the visitor with the most views and for an occasional one, reading five hundred visitors per seed and every visitor, over the same periods as `CountViewsBench` |
| `read`   | `TopViewedBench`              | `Views::top()`, plain and unique, the ten most viewed across every type and within articles, over the same periods                                                                                                                                                |
| `read`   | `TrendingBench`               | `Views::trending()`, plain and unique, the ten trending across every type and within articles, and `orderByTrending()`, plain and unique, first page of twenty, over the same periods, a period without a start reading the default eight-day horizon up to the anchor |
| `read`   | `WhereViewedBench`            | `whereViewedByVisitor()` and `whereNotViewedByVisitor()`, first page of twenty, for the visitor with the most views and for one with none, over the same periods                                                                                                  |
| `read`   | `WhereViewsCountBench`        | `whereViewsCount()` and `whereUniqueViewsCount()` at a threshold of a hundred, first page of twenty, over the same periods                                                                                                                                        |
| `read`   | `WithViewsCountBench`         | `withViewsCount()`, plain and unique, first page of twenty, over the same periods                                                                                                                                                                                 |
| `dimensions` | `DimensionsBench`         | `record()` with the five common dimensions against none, `countBy('source', limit: 10)` read from the views table for the same targets and periods as `CountViewsBench`, the sources of every article over a year read from the `views:source` rollup, and folding the last day of that rollup with the cap of twenty values and without |
| `rollup` | `FoldViewsBench`              | `views:rollup` folding the last day and the last month before the anchor again, a delete and one `insert … select` per grouping                                                                                                                                   |
| `rollup` | `RollupReadsBench`            | `count()`, `orderByViews()`, `top()` and `trending()` through the `rollup` source with every view folded into day and month rollups, for the same targets and periods as in `read`, and `anomalies()` over the last day against the four weeks before, which the day tier answers |
| `maintenance` | `AnonymiseViewsBench`     | `views:anonymise` over the last day before the anchor, in chunks of a thousand and of five thousand views, each chunk continuing after the last id of the one before; rolled back after every iteration |
| `maintenance` | `DetectSpikesBench`       | `views:detect-spikes` watching every article over the last hour against the four weeks before, from an empty episodes table, with spikes only and with drops too, which score the same rows; rolled back after every iteration |
| `maintenance` | `PruneViewsBench`         | `views:prune` deleting the oldest day of views, in chunks of a thousand and of five thousand; rolled back after every iteration |
| `maintenance` | `RecountViewsBench`       | `views:recount` writing a `views_count` column on every article, against recounting only the ten or hundred articles viewed since the last recount; rolled back after every iteration |
| `cache`  | `RememberedCountsBench`       | a `remember()` hit of `count()` for the same targets and of `forViewables()->counts()` over twenty articles, then `forgetCache()` and `flushCache()`, on the `array` store and on Redis                                                                           |
| `write`  | `RecordViewBench`             | `record()` into the full table, direct and through the sync queue                                                                                                                                                                                                 |
| `write`  | `BufferViewsBench`            | `record()` through the `redis` store, one `XADD`, and `flush()` landing a hundred, a thousand and ten thousand buffered views                                                                                                                                     |
| `write`  | `DestroyViewsBench`           | `destroy()` of a hundred, a thousand and ten thousand views                                                                                                                                                                                                       |
| `php`    | `ViewSeriesBench`             | `ViewSeries::fill()`, the PHP side of `countByInterval()`, up to a year of hourly buckets                                                                                                                                                                         |
| `php`    | `CooldownManagerBench`        | `CooldownStore::put()` on the session store, with up to ten thousand cooldowns in the session                                                                                                                                                                     |

## Results over time

[eloquent-viewable-benchmarks](https://github.com/cyrildewit/eloquent-viewable-benchmarks) keeps the runs of every
release and publishes them as a website. It checks out a release, runs that release's own benchmarks through the Make
targets here, and stores phpbench's dump with the output of `make bench-describe` and `make bench-explain
ARGS=--output=<file>`. Two things in this directory are therefore a contract with it:

- **Names.** Runs are matched across releases on the benchmark class name, the subject method and the parameter set
  name. Renaming any of them is allowed, but it ends the old line in the history and starts a new one, so do it on
  purpose and mention it in the pull request. The explain report is keyed on the same three names, which
  `tests/Unit/Benchmarks/VariantsTest.php` pins.
- **The Make interface.** `make build`, `make install`, `make bench-seed`, `make bench-describe`, `make bench-explain`
  and `make bench`, with `DRIVER`, `SIZE` and `ARGS` as documented above, and the JSON keys of `bench-describe` and
  `bench-explain --output`, which are only ever added to. A change to either needs a matching change in the results
  repository. A query in `bench-explain --output` holds `sql` and `plan` and nothing else, because the results
  repository refuses any other key there; `--execute` adds `executed` to the header and `timings_ms` beside each
  variant's `queries` instead.

## How it is put together

`benchmarks/Support/Application.php` boots a Laravel application through Testbench, outside PHPUnit, with the service
provider registered and a `benchmark` connection as the default. The connection reads the same `DB_*` variables the
test suite uses, so the Make targets only have to point them at the `bench-*` services. For SQLite the file lives on a
named volume rather than the bind-mounted project tree, which is slow on macOS.

The `redis` store benchmarks talk to the `redis` service every `composer` run starts, through phpredis unless
`REDIS_CLIENT=predis` is set.

`BenchCase` boots that application and loads the dataset description, once per benchmark process and outside the
timed region. It refuses to run while Xdebug is active, since the containers enable it for coverage by default and it
would slow everything down several times over. The Make targets turn it off.

The bench databases sit behind the `bench` Docker Compose profile, so the test targets never start them, and they get
a bigger buffer pool and shared buffers than the defaults. Without that, a medium dataset's indexes do not fit in
memory and a run measures Docker's disk instead of the query. `docker compose --profile bench down -v` throws the
seeded data away.

The code uses the `CyrildeWit\EloquentViewable\Benchmarks` namespace and is autoloaded in development only.
