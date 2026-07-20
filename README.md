# DDD Inventory — README

[![CI](https://github.com/bjohnson1279/php-ddd-inventory/actions/workflows/phpunit.yml/badge.svg)](https://github.com/bjohnson1279/php-ddd-inventory/actions/workflows/phpunit.yml)

This repository contains a small Domain-Driven Design inventory example (PHP 8.1) with in-memory and Eloquent implementations and unit + integration tests.

## Requirements

- Docker & Docker Compose
- PHP 8.1 (for local runs without Docker)
- Composer
- Git

## Quickstart (Docker — recommended)

1. Clone the repo:
   git clone https://github.com/bjohnson1279/php-ddd-inventory.git
   cd php-ddd-inventory

2. Ensure `.env` (see `.env` in repo) contains DB settings (defaults are used in CI):
   DB_CONNECTION=pgsql
   DB_HOST=db
   DB_PORT=5432
   DB_DATABASE=ddd_inventory
   DB_USERNAME=ddd_user
   DB_PASSWORD=secret

3. Start services (database + app image build):
   docker compose up -d db

4. Install PHP dependencies (inside container):
   docker compose run --rm --no-deps app bash -lc "composer install --no-interaction --prefer-dist"

5. Run unit tests:
   docker compose run --rm --no-deps app bash -lc "vendor/bin/phpunit --testdox --colors=never --exclude-group integration"

6. Run integration tests (requires DB schema):
   - Ensure the DB container is running and initial schema applied. When starting a fresh DB volume, Postgres init scripts in `docker/postgres/init/` (starting with `01_init.sql` through `11_returns.sql`) are executed automatically. To reinitialize and apply schema manually:
     docker compose down -v
     docker compose up -d db
     # apply schema from host or inside a container with psql installed
     docker compose run --rm --no-deps app bash -lc "apt-get update -y && apt-get install -y postgresql-client && PGPASSWORD=$DB_PASSWORD psql -h db -U $DB_USERNAME -d $DB_DATABASE -f docker/postgres/init/01_init.sql"

   - Then run:
     docker compose run --rm --no-deps app bash -lc "vendor/bin/phpunit tests/Integration --testdox --colors=never"

## Running tests locally (without Docker)

1. Ensure PHP 8.1 and Composer are installed.
2. Copy `.env` and update DB credentials if running integration tests against a local Postgres/TimescaleDB instance.
3. composer install
4. vendor/bin/phpunit --testdox                # runs unit + integration by default
5. vendor/bin/phpunit --exclude-group integration   # unit only

## CI (GitHub Actions)

- Workflow: `.github/workflows/phpunit.yml` runs two jobs:
  - `unit`: runs fast unit tests and excludes tests marked with `@group integration`.
  - `integration`: spins up a `timescale/timescaledb:latest-pg15` service, applies database migrations from `docker/postgres/init/` (starting with `01_init.sql`), and runs `tests/Integration`.

Integration tests are annotated with `@group integration` (see tests/Integration) so unit CI stays fast.

## API

A short API summary is available in docs/ENDPOINTS.md and a machine-readable OpenAPI 3.0 specification is shipped at docs/openapi.yaml.

Quick endpoints summary:

- POST /api/inventory/receive — receive stock for a SKU at a location. Payload: { sku, quantity, location_id }
- POST /api/inventory/dispatch — dispatch stock for a SKU at a location. Payload: { sku, quantity, location_id }
- GET  /api/inventory/{sku}/stock — get stock level; optional query ?location_id=
- POST /api/inventory-counts — start an inventory count (returns count_id)
- POST /api/inventory-counts/{count_id}/items — record item counts. Payload: { sku, quantity }
- POST /api/inventory-counts/{count_id}/complete — complete a count and reconcile stock
- POST /api/catalog/products — create catalog product. Payload: { name, description, department }
- POST /api/catalog/products/{productId}/variants — add variant to a product. Payload: { sku, attributes, price }

For full details and example curl commands see docs/ENDPOINTS.md.

## Repositories & switching implementations

- ServiceContainer provides factory methods to get repositories. By default:
  - In-memory implementations are used for most local development and unit tests.
  - Eloquent implementations exist and integration tests exercise them.

To change the wiring, update `src/Infrastructure/ServiceContainer.php`.

## Adding tests

- Unit tests: place under `tests/Unit`.
- Integration tests: place under `tests/Integration` and annotate the test class or method with `/** @group integration */`.

## Troubleshooting

- DB schema not applied: remove the DB volume and recreate to force init scripts:
  docker compose down -v && docker compose up -d db

- Composer issues in CI: ensure `composer.lock` is present (this repo commits it) and the workflow caches are not stale.

- If a test fails referencing environment values, ensure `.env` is loaded in the test bootstrap (integration bootstrap uses Dotenv).

## Development notes

- Domain events currently use lightweight placeholders; consider implementing concrete event classes and dispatchers for production.
- UUID generation in integration tests uses a helper; consider using `ramsey/uuid` in production.

--
Generated by repository automation. If you want a shorter quickstart or additional developer scripts (Makefile targets, docker helper scripts) added to README, say which ones and they will be added.
