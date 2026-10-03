# Well documented Makefiles
DEFAULT_GOAL := help

# None of these targets produce a file. `build` in particular would otherwise
# clash with the build/ directory and never run.
.PHONY: help build install lint rector ready test test-arch test-unit test-feature test-samples test-lint test-types deptrac-graph test-type-coverage test-coverage test-mutation test-mysql test-mariadb test-pgsql test-drivers db-stop bench-db bench-seed bench-indexes bench bench-baseline bench-compare bench-explain bench-describe
help:
	@awk 'BEGIN {FS = ":.*##"; printf "\nUsage:\n  make \033[36m<target>\033[0m\n"} /^[a-zA-Z0-9_-]+:.*?##/ { printf "  \033[36m%-40s\033[0m %s\n", $$1, $$2 } /^##@/ { printf "\n\033[1m%s\033[0m\n", substr($$0, 5) } ' $(MAKEFILE_LIST)

build: ## Build all docker images. Specify the command e.g. via make build ARGS="--build-arg PHP=8.5"
	docker compose build $(ARGS)

##@ [Application]
install: ## Install the composer dependencies
	docker compose run --rm composer install

lint: ## Fix the code style
	docker compose run --rm composer lint

rector: ## Run Rector
	docker compose run --rm composer rector

ready: ## Fix with Rector and the linter, then run the static analysis and the tests
	XDEBUG_MODE=off docker compose run --rm composer ready

test: ## Run the tests in parallel (SQLite in memory)
	XDEBUG_MODE=off docker compose run --rm composer test

test-arch: ## Run the architecture tests
	docker compose run --rm composer test:arch

test-unit: ## Run the unit tests (no Laravel application is booted)
	docker compose run --rm composer test:unit

test-feature: ## Run the feature tests (booted through Testbench)
	docker compose run --rm composer test:feature

test-samples: ## Run the samples' tests (booted through Testbench)
	docker compose run --rm composer test:samples

test-lint: ## Check the code style without fixing it
	docker compose run --rm composer test:lint

test-types: ## Run the static analysis
	docker compose run --rm composer test:types

deptrac-graph: ## Draw the dependencies between layers to build/deptrac.png
	docker compose run --rm composer deptrac:graph

test-type-coverage: ## Run the tests with type coverage and fail below 100%
	docker compose run --rm composer test:type-coverage

test-coverage: ## Run the tests with coverage and fail below 100% (writes build/coverage.xml)
	docker compose run --rm composer test:coverage

test-mutation: ## Run mutation testing (applies the temporary pest-plugin-mutate patch)
	./scripts/patch-pest-mutate.sh
	docker compose run --rm composer test:mutation

##@ [Database drivers]
# `make test` uses SQLite in memory. These targets run the same suite against a
# real driver, the way the CI matrix does. Each one starts its service and waits
# for the healthcheck first. Ports are the ones inside the compose network, so
# MariaDB is on 3306 here even though CI maps it to 3307 on the host.
#
# They run serially. The parallel workers of `make test` each get their own
# in-memory SQLite database, but against a server they would share one and
# drop each other's tables on every refresh.

test-mysql: ## Run the tests against MySQL
	docker compose up -d --wait mysql
	DB_CONNECTION=mysql DB_HOST=mysql DB_PORT=3306 docker compose run --rm composer test:serial

test-mariadb: ## Run the tests against MariaDB
	docker compose up -d --wait mariadb
	DB_CONNECTION=mariadb DB_HOST=mariadb DB_PORT=3306 docker compose run --rm composer test:serial

test-pgsql: ## Run the tests against PostgreSQL
	docker compose up -d --wait postgres
	DB_CONNECTION=pgsql DB_HOST=postgres DB_PORT=5432 docker compose run --rm composer test:serial

test-drivers: test test-mysql test-mariadb test-pgsql ## Run the tests against every supported driver

db-stop: ## Stop the database services, the benchmark ones included
	docker compose --profile bench down

##@ [Benchmarks]
# phpbench against a seeded dataset, see benchmarks/README.md. DRIVER picks the
# database: sqlite (default) uses a file on a named volume, the others start
# their bench-* service first. Xdebug is turned off, the default coverage mode
# would slow PHP down several times over and make the numbers meaningless.
#
#   make bench-seed DRIVER=mysql SIZE=medium
#   make bench DRIVER=mysql ARGS="--group=read"
#   make bench-baseline TAG=main && git switch my-branch && make bench-compare TAG=main

DRIVER ?= sqlite
SIZE ?= small
TAG ?= baseline
INDEXES ?= none

BENCH_ENV_sqlite  := DB_CONNECTION=sqlite
BENCH_ENV_mysql   := DB_CONNECTION=mysql DB_HOST=bench-mysql DB_PORT=3306
BENCH_ENV_mariadb := DB_CONNECTION=mariadb DB_HOST=bench-mariadb DB_PORT=3306
BENCH_ENV_pgsql   := DB_CONNECTION=pgsql DB_HOST=bench-postgres DB_PORT=5432
BENCH_SERVICE_mysql   := bench-mysql
BENCH_SERVICE_mariadb := bench-mariadb
BENCH_SERVICE_pgsql   := bench-postgres

BENCH_SERVICE = $(BENCH_SERVICE_$(DRIVER))
BENCH_RUN = XDEBUG_MODE=off DB_DATABASE=benchmark $(BENCH_ENV_$(DRIVER)) docker compose run --rm composer

bench-db: ## Start the benchmark database for DRIVER, nothing for sqlite
ifneq ($(BENCH_SERVICE),)
	docker compose --profile bench up -d --wait $(BENCH_SERVICE)
endif

bench-seed: bench-db ## Seed the benchmark dataset, e.g. make bench-seed DRIVER=mysql SIZE=medium
	$(BENCH_RUN) bench:seed -- --size=$(SIZE) $(ARGS)

bench-indexes: bench-db ## Set the optional indexes, e.g. make bench-indexes INDEXES=visitor,type-viewed-at
	$(BENCH_RUN) bench:indexes -- --set=$(INDEXES)

bench: bench-db ## Run the benchmarks, e.g. make bench DRIVER=pgsql ARGS="--group=read"
	$(BENCH_RUN) bench -- $(ARGS)

bench-baseline: bench-db ## Run the benchmarks and store the result under TAG (default: baseline)
	$(BENCH_RUN) bench -- --store --tag=$(TAG) $(ARGS)

bench-compare: bench-db ## Run the benchmarks against the stored TAG and fail on a subject that got over 10% slower
	$(BENCH_RUN) bench -- --ref=$(TAG) --assert="mode(variant.time.avg) <= mode(baseline.time.avg) +/- 10%" $(ARGS)

bench-explain: bench-db ## Print the SQL and query plan of every read benchmark, ARGS=--output=<file> writes JSON too, --analyze executes, --group=<name> picks a group
	$(BENCH_RUN) bench:explain -- $(ARGS)

bench-describe: bench-db ## Print the seeded dataset and the database as JSON, ARGS=--output=<file> writes a file
	$(BENCH_RUN) bench:describe -- $(ARGS)
