# Contributing

Thank you for considering contributing to Eloquent Viewable!

We accept contributions via pull requests on [GitHub]. Please review these guidelines before submitting any pull
requests.

## Guidelines

* Code style is enforced with [Pint](https://laravel.com/docs/pint); run `make lint` before committing.
* One pull request per feature (send multiple if you want to do more than one thing).
* Add tests if you've added something new (ensure that the current tests pass).
* Send a coherent commit history (make sure each individual commit in your pull request is meaningful).
* Document any change in behaviour (make sure the `README.md` is kept up-to-date).
* Strictly follow our [Git Commit Guidelines](#git-commit-guidelines)!
* Please remember that we follow [SemVer](http://semver.org/).

### Git Commit Guidelines

We follow the [Conventional Commits](https://www.conventionalcommits.org/) specification for our git commit messages. A
consistent format keeps the commit history readable and makes it easy to generate the changelog.

#### Commit Message Format

```html
<type>(<scope>): <subject>
<BLANK LINE>
<body>
<BLANK LINE>
<footer>
```

> Any line of the commit message cannot be longer than 100 characters!
> This allows the message to be easier to read on GitHub as well as in various git tools.

##### Type

Must be one of the following:

* **feat:** a new feature
* **fix:** a bug fix
* **style:** changes that do not affect the meaning of the code (white-space, formatting, missing semi-colons, etc.)
* **refactor:** a code change that neither fixes a bug nor adds a feature
* **test:** adding missing tests
* **chore:** changes to the build process or auxiliary tools and libraries such as documentation generation

##### Scope

The scope could be anything specifying the place of the commit change.

##### Subject

The subject contains a succinct description of the change:

* use the imperative, present tense: "change" not "changed" nor "changes"
* don't capitalize first letter
* no dot (.) at the end

##### Body

Just as in the **subject**, use the imperative, present tense: "change" not "changed" nor "changes" The body should
include the motivation for the change and contrast this with previous behavior.

##### Footer

The footer is optional and may contain one or more footers, each on its own line. Use it to reference GitHub issues
that this commit closes (e.g. `Closes #123`) and to describe breaking changes.

A breaking change must be signalled in one of two ways:

* append a `!` after the type/scope, e.g. `feat(views)!: drop support for Laravel 10`, or
* start a footer line with `BREAKING CHANGE:` followed by a description of what changed.

Both may be combined. The description explains what breaks and what community users must do to adapt, and is
highlighted in the changelog.

```
feat(views)!: drop support for Laravel 10

BREAKING CHANGE: the minimum supported Laravel version is now 11. Upgrade your application before updating.
```

## Local Development

Development runs entirely inside Docker, so you don't need PHP or [Composer](https://getcomposer.org/) installed
locally. Everything is driven through the `Makefile`. Run `make` (or `make help`) at any time to see the available
targets.

Before doing anything else, build the images and install the dependencies:

```bash
make build
make install
```

By default the images use PHP 8.5. To build against a different version, pass it through `ARGS`:

```bash
make build ARGS="--build-arg PHP=8.4"
```

### Common tasks

| Command                   | Description                                                                 |
|---------------------------|-----------------------------------------------------------------------------|
| `make ready`              | Run Rector and Pint, then the static analysis, type coverage and test suite |
| `make lint`               | Fix code style with [Pint](https://laravel.com/docs/pint)                   |
| `make rector`             | Run [Rector](https://getrector.com/)                                        |
| `make test`               | Run the [Pest](https://pestphp.com/) test suite                             |
| `make test-arch`          | Run only the architecture tests                                             |
| `make test-unit`          | Run only the unit tests                                                     |
| `make test-feature`       | Run only the feature tests                                                  |
| `make test-lint`          | Check code style without fixing it                                          |
| `make test-types`         | Run the [PHPStan](https://phpstan.org/) static analysis                     |
| `make deptrac-graph`      | Draw the dependencies between layers (see below)                            |
| `make test-type-coverage` | Run the type coverage check (fails below 100%)                              |
| `make test-coverage`      | Run the suite with line coverage (fails below 100%)                         |
| `make test-mutation`      | Run mutation testing (see note below)                                       |

Every target in that table maps onto the Composer script of the same name, with `-` where the script has `:`, run in
the `composer` container. `make test-coverage` runs `docker compose run --rm composer test:coverage`. If you prefer,
you can invoke those scripts directly:

```bash
docker compose run --rm composer test
```

### Dependency graph

`make deptrac-graph` uses [Deptrac](https://deptrac.github.io/deptrac/) to draw the dependencies between the layers
of `src/` to `build/deptrac.png`. Each edge shows how many references it stands for. A red edge points up the stack,
or sideways between two modules, and is worth a second look. `deptrac.yaml` defines the layers. The Pest arch tests
in `tests/Architecture` are what enforce the rules, so the graph never fails a build.

### Running against another database

`make test` uses SQLite in memory. CI also runs the suite against MySQL, MariaDB and Postgres, so anything that
touches a query or a bucket grammar is worth checking against them before you open a pull request.

| Command             | Description                                |
|---------------------|--------------------------------------------|
| `make test-mysql`   | Run the suite against MySQL                |
| `make test-mariadb` | Run the suite against MariaDB              |
| `make test-pgsql`   | Run the suite against PostgreSQL           |
| `make test-drivers` | Run the suite against all four drivers     |
| `make db-stop`      | Stop the database services                 |

These have no matching Composer script. Each one starts its database service, waits for the healthcheck and then sets
`DB_CONNECTION`, `DB_HOST` and `DB_PORT` for the run, which you can also do by hand:

```bash
DB_CONNECTION=mysql DB_HOST=mysql DB_PORT=3306 docker compose run --rm composer test
```

A handful of tests assert raw SQL strings or read a SQLite query plan. Those skip on the other drivers, so the counts
differ between runs.

The suite is split in three. Tests in `tests/Arch` are Pest arch expectations about the source tree. Tests in
`tests/Unit` extend plain PHPUnit and never boot a Laravel application, so they run in well under a second. Tests in
`tests/Feature` extend the Testbench test case and get a booted application with a database. Put a test in
`tests/Feature` when it needs the container, a database, a facade or the service provider.

Feature tests share one database per process. The schema is created once and `RefreshDatabase` rolls each test back,
which does not reset auto-increment counters, so ids keep climbing from one test to the next. Assert on a model's own
key rather than a literal id.

### Benchmarks

The `benchmarks` directory holds a [phpbench](https://phpbench.readthedocs.io/) suite that times the paths that get
expensive as the `views` table grows, against a seeded dataset of a million to fifty million rows, on every supported
driver. It does not run with the tests or in CI. Use it when you change a query, a grammar or anything on the recording
path: store a run on `main`, switch to your branch and compare.

```bash
make bench-seed DRIVER=mysql SIZE=medium
git switch main && make bench-baseline DRIVER=mysql TAG=main
git switch my-branch && make bench-compare DRIVER=mysql TAG=main
```

`make bench-explain` prints the SQL and the query plan of every read path, which is the quickest way to see whether a
query still uses the composite index. [`benchmarks/README.md`](benchmarks/README.md) documents the dataset, the
targets and the optional indexes.

When you make a pull request, the tests will be automatically run again
by [GitHub Actions](https://github.com/cyrildewit/eloquent-viewable/actions).

[GitHub]: https://github.com/cyrildewit/laravel-page-view-counter/pulls
