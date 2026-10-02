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

Every view is recorded before `2026-01-01 00:00:00`. The benchmarks build their periods relative to that anchor, so
"the past 30 days" covers the same rows on every run, whenever it happens. The write benchmarks remove the rows they
add.

Pass `--seed` through `ARGS` to seed a different draw of the same shape:

```bash
make bench-seed SIZE=medium ARGS="--seed=7"
```

## Optional indexes

The README suggests two indexes for apps that need them. The migration does not create them, so a seeded dataset starts
without. `make bench-indexes` adds or drops them in place, which is far quicker than seeding again, and the same
benchmarks measure the difference.

| Name             | Index                                                                               | Serves                                       |
|------------------|-------------------------------------------------------------------------------------|----------------------------------------------|
| `visitor`        | `(viewable_type, viewable_id, viewed_at, visitor)`, `INCLUDE (visitor)` on Postgres | `unique()` counts without touching the table |
| `type-viewed-at` | `(viewable_type, viewed_at)`                                                        | counts over a whole type within a period     |

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

```bash
make bench-explain DRIVER=pgsql ARGS=--analyze
make bench-explain DRIVER=mysql ARGS="--output=build/queries.json"
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

| Group   | Benchmark                   | Measures                                                                                                                              |
|---------|-----------------------------|---------------------------------------------------------------------------------------------------------------------------------------|
| `read`  | `CountViewsBench`           | `count()` and `unique()->count()`, for the hot article, the cold article and the whole type, over all time, a year, 30 days and a day |
| `read`  | `CountViewsByIntervalBench` | `countByInterval()`, plain and unique, from a week of hours to two years of months                                                    |
| `read`  | `OrderByViewsBench`         | `orderByViews()` and `orderByUniqueViews()`, first page of twenty, over the same periods                                              |
| `write` | `RecordViewBench`           | `record()` into the full table, direct and through the sync queue                                                                     |
| `write` | `BufferViewsBench`          | `record()` through the `redis` store, one `XADD`, and `flush()` landing a hundred, a thousand and ten thousand buffered views         |
| `write` | `DestroyViewsBench`         | `destroy()` of a hundred, a thousand and ten thousand views                                                                           |
| `php`   | `ViewSeriesBench`           | `ViewSeries::fill()`, the PHP side of `countByInterval()`, up to a year of hourly buckets                                             |
| `php`   | `CooldownManagerBench`      | `CooldownStore::put()` on the session store, with up to ten thousand cooldowns in the session                                         |

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
  repository.

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
