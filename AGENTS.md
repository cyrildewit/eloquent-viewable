# Agent instructions

## Nothing runs on the host

There is no PHP or Composer on the host machine. `php`, `composer`, `vendor/bin/pest` and
`vendor/bin/phpstan` will all fail with "command not found". Do not install them, and do not fall back
to reading config files to work out what a command would have done.

Every command goes through `make`, which runs it in the `composer` container:

```bash
make install            # install dependencies (run once, or after composer.json changes)
make ready              # rector, pint, phpstan, type coverage, tests
make lint               # pint, fixes style in place
make rector             # rector
make test               # the full Pest suite
make test-unit          # tests/Unit
make test-feature       # tests/Feature
make test-arch          # tests/Arch
make test-samples       # the tests next to each sample in samples/
make test-lint          # pint --test, checks style without fixing
make test-types         # phpstan
make deptrac-graph      # draws the dependencies between layers to build/deptrac.png
make test-type-coverage # type coverage, fails below 100%
make test-coverage      # the suite with coverage, fails below 100%
make test-mutation      # mutation testing, see below
make test-mysql         # the suite against MySQL
make test-mariadb       # the suite against MariaDB
make test-pgsql         # the suite against PostgreSQL
make test-drivers       # the suite against all four drivers
make db-stop            # stop the database services
```

If `docker compose` complains that the image is missing, run `make build` first.

## Running a subset

The Make targets take no arguments. To pass flags to Pest, call the Composer script directly:

```bash
docker compose run --rm composer test -- --filter=CooldownManager
```

## Before you hand work back

Run `make ready`.

CI checks two more things that `make ready` does not. It enforces 100% line coverage on the SQLite
leg, so new code needs tests covering it; `make test-coverage` runs the same check locally. And it runs
the suite against MySQL, MariaDB and Postgres as well as SQLite. `make test` only covers SQLite, so
anything touching a query or a bucket grammar needs the other drivers too:

```bash
make test-drivers
```

Each driver target starts its database service and waits for the healthcheck, so the first run is slow.
`make db-stop` shuts the services down again.

## Where a test belongs

Put a test in `tests/Unit` unless it needs something only a booted application provides: the
container, the database, a facade or the service provider. Those go in `tests/Feature`. The unit
suite finishes in well under a second and that is worth protecting. `tests/Arch` holds Pest
arch expectations about the shape of `src/`.

## Things that will bite you

Mutation testing applies a patch to a vendored package first, so run `make test-mutation` rather than
`composer test:mutation`.

Feature tests share one database per process. The schema is created once and `RefreshDatabase` rolls
each test back, which does not reset auto-increment counters, so ids keep climbing from one test to the
next. Never assert on a literal primary key; read the key off the model instead.

Docker Compose reads a root `.env` for variable interpolation, so a stray one silently repoints
`make test` at another driver. It is gitignored, but do not create one.

Fix PHPStan errors rather than ignoring them.

## Conventions

Commit messages follow Conventional Commits. `CONTRIBUTING.md` has the format, the allowed types and
how to signal a breaking change.

Document behaviour changes in `README.md`, and add an entry to `CHANGELOG.md` under Unreleased.
A breaking change also needs a section in `UPGRADING.md`, under the heading for the version being
worked on, that tells users what broke and what to change.
