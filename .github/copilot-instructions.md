# Copilot instructions for php-ddd-inventory

This file tells Copilot sessions how to build, test, and reason about this repository.

1) Build, test, and (lack of) lint commands

- Requirements: PHP >= 8.1, Composer, Docker & Docker Compose (recommended).

- Install dependencies (host):
  composer install

- Install dependencies (Dockerized app):
  docker compose run --rm --no-deps app bash -lc "composer install --no-interaction --prefer-dist"

- Build app image:
  docker compose build app

- Bring DB up (recommended for integration tests):
  docker compose up -d db

- Run full test suite (host):
  vendor/bin/phpunit --testdox

- Run unit tests only (exclude integration group):
  vendor/bin/phpunit --exclude-group integration --testdox

- Run integration tests (requires DB + schema):
  vendor/bin/phpunit tests/Integration --testdox --colors=never

- Running a single test file (host):
  vendor/bin/phpunit tests/Unit/Path/ToTest.php --testdox

- Running a single test method (host):
  vendor/bin/phpunit --filter testMethodName --testdox

- Dockerized equivalents (run inside app container):
  docker compose run --rm --no-deps app bash -lc "vendor/bin/phpunit tests/Unit/Path/ToTest.php --testdox"

- Makefile convenience target (runs tests inside docker):
  make test

- DB init / reinitialize (used by CI and integration tests):
  docker compose down -v && docker compose up -d db
  # schema is applied from docker/postgres/init/init.sql

- Lint: no repository-wide linter configured (no phpcs/php-cs-fixer configured). Do not assume automatic linting.

2) High-level architecture (big picture)

- This is a small Domain-Driven Design (DDD) PHP app organized into three top-level layers under src/:
  - src/Application — application services, use-cases, and DTOs.
  - src/Domain — domain entities, value objects, and domain logic.
  - src/Infrastructure — persistence, adapters, and wiring (ServiceContainer).

- PSR-4 namespace: InventoryApp\ mapped to src/ (see composer.json).

- Repositories and implementations:
  - ServiceContainer (src/Infrastructure/ServiceContainer.php) provides factory wiring for repositories.
  - In-memory implementations are the default for local development and unit tests.
  - Eloquent implementations exist and are exercised by integration tests against Postgres.

- Tests:
  - Unit tests live under tests/Unit.
  - Integration tests live under tests/Integration and are annotated with @group integration.
  - CI runs a fast unit job (excludes integration) and a separate integration job that starts Postgres and applies docker/postgres/init/init.sql.

- API docs: docs/ENDPOINTS.md contains endpoint summaries; a machine-readable OpenAPI 3.0 spec is provided at docs/openapi.yaml.

3) Key conventions and repo-specific patterns

- Use ServiceContainer to switch implementations. To change wiring, edit src/Infrastructure/ServiceContainer.php.

- Tests rely on the convention that integration tests are annotated with /** @group integration */. Use PHPUnit groups to include/exclude integration tests in CI and local runs.

- Database bootstrap: Postgres initial schema is applied from docker/postgres/init/init.sql by the DB container on first startup. To reapply, remove the DB volume and restart the DB service (see commands above).

- Tests/database environment: Copy .env (repo includes an example) and set DB_* values when running integration tests locally; CI uses repo defaults.

- Namespacing and PSR-4: All PHP code uses InventoryApp\ namespace; new classes should follow PSR-4 placement under src/.

- Domain events & placeholders: The repo contains lightweight placeholders for domain events (see README). Expect simple event abstractions rather than a full dispatcher implementation.

- UUID helper & utilities: Integration tests use a helper for UUID generation; replacing with a library (e.g., ramsey/uuid) is suggested in README but not required for tests.

4) Files and docs Copilot should read first

- README.md — quickstart, testing, and CI notes.
- src/Infrastructure/ServiceContainer.php — wiring and repository selection.
- tests/Unit and tests/Integration — test examples and intended usage.
- docker/postgres/init/init.sql — DB schema used by integration tests.
- docs/ENDPOINTS.md and docs/openapi.yaml — API surface and examples.

MCP servers

- Would you like an MCP server configured (e.g., Playwright or other CI test runners)? If so, specify which server or testing focus to configure.

Summary

Created .github/copilot-instructions.md with build/test commands, architecture overview, and repo-specific conventions. Want any adjustments or extra coverage (e.g., more examples for running single tests, or adding linter suggestions)?
