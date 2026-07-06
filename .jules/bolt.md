## 2026-06-21 - N+1 Queries During Bulk Domain Event Emittance
**Learning:** In PHP, event listeners inherently process one event at a time. If a batch operation emits multiple domain events (e.g., `SaleProcessed`), listeners attached to that event (like `SyncStockToShopify`) will trigger sequentially, potentially executing database lookup queries for each iteration, causing an N+1 performance bottleneck.
**Action:** When a UseCase (like `ProcessSaleBatch` or `ShopifyOrderMapper`) triggers multiple domain events that will cause a downstream listener to perform lookups, inject the repository into the UseCase or Mapper and proactively bulk-preload the required data into the repository's in-memory static/instance cache before executing the batch.

## 2024-06-22 - Static caching in event listeners
**Learning:** Batch processing can cause an N+1 query problem if event listeners fetch the same product entity repeatedly from the database for each individual event.
**Action:** Use a static array cache within synchronous listeners to temporarily memoize database reads during batch execution, significantly improving performance.

## 2024-06-22 - Avoid N+1 queries in synchronous listeners via static caching
**Learning:** Bulk operations that dispatch multiple events trigger listeners repeatedly, causing N+1 queries. We can't always depend on the entity layer to fix this if the events run synchronously.
**Action:** Use a static array cache paired with `beginBatch()` and `endBatch()` methods in the listener to pre-load queries (using chunked `whereIn` clauses). Invoke the batch wrapper directly from the use cases (in a try/finally block) to avoid unbounded static cache memory leaks in queue workers.

## 2024-06-22 - Exception handling in static preloads
**Learning:** Silently catching database exceptions during cache preloading in a queued listener causes permanent data inconsistency by making the worker skip critical logic and mark the job as successful.
**Action:** When wrapping bulk database queries in `try/catch` for testing environments, ensure the fallback strictly checks for isolated test scenarios (like `sqlite` driver and 'no such table' messages) and re-throws the exception otherwise.

## 2026-06-22 - Prevent N+1 queries during Shopify webhook batch processing
**Learning:** Translating external webhooks into domains payloads can cause N+1 database queries if mapping repositories are queried in a loop.
**Action:** When translating line items, implement and invoke preload methods (e.g. `preloadSkuIds`) using `whereIn` queries to populate the repository's in-memory cache before iterating through items.

## 2026-06-25 - TimescaleDB Hypertables and Composite Primary Keys
**Learning:** Standard single-column primary keys (e.g. `id UUID PRIMARY KEY`) are incompatible with TimescaleDB hypertables, which require any primary key or unique constraint to include the time-partitioning column.
**Action:** When working with append-only time-series tables (like `ledger_entries`, `inventory_transactions`, or `dispatch_records`):
- Ensure that the primary key is defined as a composite key containing both the unique ID and the timestamp column (e.g. `PRIMARY KEY (id, occurred_at)` or `@@id([id, occurredAt])`).
- Convert the table to a hypertable immediately upon creation/migration using `SELECT create_hypertable('table_name', 'time_column', if_not_exists => TRUE);`.
- For Node.js/Prisma setups, ensure the datasource provider is set to PostgreSQL (not SQLite) to maintain database parity across all service variants.

## 2026-06-27 - Eloquent/Query Builder Mass Selection Overhead
**Learning:** In Laravel's Query Builder (and Eloquent), calling `->get()` without specifying columns fetches every column from the table (`SELECT *`). For large tables like `products` or `inventory_transactions`, this significantly increases database payload size, network latency, and memory consumption during object instantiation, especially for large tenant reporting.
**Action:** When writing queries for batch processing, mapping, or reporting (like `ReportController`), explicitly list only the required columns in the `->get(['id', 'sku', ...])` method to minimize the memory footprint and execution time.

## 2026-06-27 - Parent Folder Traversal for Dotenv in Standalone Scripts
**Learning:** Standalone scripts (like queue-worker.php) that bootstrap their database connections through helper files might execute in a different working directory or contain hardcoded parent directory paths (e.g. `../../../../`) that point outside the project directory. When this occurs, `.env` loading fails silently, triggering fallbacks like empty in-memory SQLite instances or incorrect database connections that lack tables.
**Action:** Ensure that standalone bootstrap files resolve paths relative to the current file using `__DIR__` and traverse exactly to the project's root folder where the `.env` resides (e.g. `__DIR__ . '/../../../'`), matching the paths verified in unit/integration test bootstraps.
## 2024-06-28 - Optimizing Multiple Aggregate Queries
**Learning:** Performing multiple `sum()` calls on the same Eloquent query builder results in multiple database round-trips for the same dataset, creating a performance bottleneck when checking stock levels.
**Action:** Use `selectRaw` with `COALESCE(SUM(...), 0)` to combine multiple aggregations into a single query and reduce database overhead.
