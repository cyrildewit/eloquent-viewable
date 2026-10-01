# Well documented Makefiles
DEFAULT_GOAL := help
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
	docker compose run --rm composer ready

test: ## Run the tests (SQLite in memory)
	docker compose run --rm composer test

test-arch: ## Run the architecture tests
	docker compose run --rm composer test:arch

test-unit: ## Run the unit tests (no Laravel application is booted)
	docker compose run --rm composer test:unit

test-feature: ## Run the feature tests (booted through Testbench)
	docker compose run --rm composer test:feature

test-lint: ## Check the code style without fixing it
	docker compose run --rm composer test:lint

test-types: ## Run the static analysis
	docker compose run --rm composer test:types

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

test-mysql: ## Run the tests against MySQL
	docker compose up -d --wait mysql
	DB_CONNECTION=mysql DB_HOST=mysql DB_PORT=3306 docker compose run --rm composer test

test-mariadb: ## Run the tests against MariaDB
	docker compose up -d --wait mariadb
	DB_CONNECTION=mariadb DB_HOST=mariadb DB_PORT=3306 docker compose run --rm composer test

test-pgsql: ## Run the tests against PostgreSQL
	docker compose up -d --wait postgres
	DB_CONNECTION=pgsql DB_HOST=postgres DB_PORT=5432 docker compose run --rm composer test

test-drivers: test test-mysql test-mariadb test-pgsql ## Run the tests against every supported driver

db-stop: ## Stop the database services
	docker compose down
