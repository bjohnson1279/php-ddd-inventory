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

## 2026-06-30 - Prevent N+1 queries during Shopify inventory auditing
**Learning:** In audit scripts like `AuditProcessorService::runAudit()`, iterating over Shopify mappings and performing nested aggregate queries (`LedgerEntryModel::where(...)->sum()`) inside loops causes severe N+1 performance bottlenecks.
**Action:** When performing cross-system comparisons, pre-fetch all needed products using `whereIn` and pre-calculate all local aggregates using a grouped raw select (`selectRaw("..., SUM(quantity) as sum_qty")->groupBy(...)`), then map the data in memory.

## 2026-06-30 - N+1 Query in DemandForecaster Bulk Report Generation
**Learning:** Generating the demand planning report iterates over every SKU in a location and invokes `calculateSalesVelocity()`. This function was fetching the product entity using `findBySku()` independently for each SKU iteration, despite the fact that `getDemandPlanningReport()` had already pre-fetched all those products via a single `findBySkus()` bulk query. This resulted in a redundant N+1 lookup for product entities.
**Action:** When a method like `calculateSalesVelocity()` is called in a loop, modify its signature to accept an optional pre-fetched aggregate/entity (e.g., `?Product $product = null`). Use this injected entity when present instead of running a redundant database query (`$product = $product ?? $this->productRepo->findBySku($sku);`), dramatically reducing query load during bulk reporting.

## 2026-07-06 - N+1 Queries in Demand Forecasting loops
**Learning:** Looping over an array of domain entities (like SKUs) and invoking multiple database lookups per iteration (such as fetching product details, ledger entries, and replenishment rules) creates a severe N+1 performance bottleneck that degrades exponentially as the dataset grows.
**Action:** When calculating derived insights or building reports for a collection of entities, identify iterative database lookups and replace them with bulk operations using `whereIn` queries. Pass the pre-fetched, batched data (e.g., grouped in-memory arrays) to the calculation logic instead of performing lookups inside the loop.

## 2024-06-28 - Optimizing Multiple Aggregate Queries
**Learning:** Performing multiple `sum()` calls on the same Eloquent query builder results in multiple database round-trips for the same dataset, creating a performance bottleneck when checking stock levels.
**Action:** Use `selectRaw` with `COALESCE(SUM(...), 0)` to combine multiple aggregations into a single query and reduce database overhead.
## 2024-05-18 - Fix N+1 query in AuditProcessorService
**Learning:** Found an N+1 query nested in double loops (SKUs and locations mappings) fetching `LedgerEntryModel::sum('quantity')` sequentially. A single query across products/locations grouping by metadata json attribute (using `metadata->>'locationId'`) can fetch all needed totals upfront.
**Action:** Extract database querying out of double loops using `whereIn` and `groupBy` into a multi-dimensional array mapping.
## 2024-07-07 - Pre-fetch Mapped Journal Entries to Avoid N+1 DB Queries
**Learning:** Checking for mapping existence in the `AuditProcessorService` inside a `foreach` loop results in $4N$ database queries, severely impacting audit performance for large datasets.
**Action:** Optimize by plucking journal IDs before the loop and batch fetching existing mappings and discrepancies using `whereIn` queries. In-memory checks via `in_array` avoid looping over DB interactions, returning the operations to $O(1)$ complexity.

## 2024-06-25 - Batch Saving Cost Layers inside DisassembleKit Loop
**Learning:** Resolving N+1 database queries by batch saving domain entities (like `InventoryCostLayer`) outside of iterations avoids significant database roundtrips.
**Action:** When working inside loops constructing multiple entities, initialize an array to hold the instantiated objects, append them in the iteration, and call `saveBatch()` (if provided by the repository) rather than calling `save()` independently per entity. Ensure test mocks reflect `saveBatch` invocations sequentially using `.withConsecutive` or explicit `.callback` matching logic.

## 2024-06-12 - Batching Ledger Entries to prevent N+1 queries
**Learning:** In the domain architecture, components in bulk operations (like opening balances or kit assembly/disassembly) shouldn't iteratively call `LedgerRepositoryInterface->append()`. This leads to severe N+1 database INSERTs in `EloquentLedgerRepository`.
**Action:** When handling multiple components or entries in a loop, aggregate the `LedgerEntry` objects into an array and use the newly added `appendAll(array $entries)` method on the repository interface to perform a single batch INSERT.
## 2024-07-13 - Eliminate N+1 DB queries in bulk stock updates via SyncStockToShopify batching
**Learning:** When processing bulk operations (like complete inventory counts, sales, or returns), dispatching domain events individually inside loops can trigger repetitive database lookups within listeners like `SyncStockToShopify`. Specifically, syncing stock to Shopify triggers queries on `shopify_sku_mappings` and `shopify_location_mappings` for every single event.
**Action:** Always wrap event dispatch loops for bulk domain operations with `\InventoryApp\Application\Inventory\Listeners\SyncStockToShopify::beginBatch(...)` and `endBatch()` inside a `try...finally` block. This allows the listener to pre-fetch Shopify metadata mapping in a single query, eliminating the N+1 problem.
## 2026-07-18 - Optimize string hashing with crc32
**Learning:** Replaced a manual character-by-character string hashing loop with PHP's native `crc32()` function. This yielded an enormous (~98%) performance improvement because native functions implemented in C are significantly faster than iterating over string characters in PHP userland.
**Action:** When a deterministic numeric hash of a string is needed for arbitrary distribution (e.g., generating fallback coordinates) and the specific hash value isn't strictly mandated by an external contract, always prefer native PHP hashing functions like `crc32()` over manual implementations.
## 2024-05-24 - Batch Fetching Cost Layers for Kit Components

**Learning:** Replacing an N+1 query inside a kit component loop with a batch fetch method using `whereIn` grouped by `variant_id` drastically reduces database queries without breaking fallback functionality for active layers when handling expected domain exceptions.
**Action:** Always inspect loops containing repository fetches for batch-fetching opportunities in application use cases. Ensure fallback states are preserved when utilizing the new batch queries.

## 2026-07-18 - Fix N+1 queries in DemandForecaster
**Learning:** Found multiple N+1 queries in `DemandForecaster`. `generateDemandForecast` fetched ledger entries and then passed them to `calculateSalesVelocity` which fetched them again. `getDemandPlanningReport` looped over SKUs and inside the loop, fetched policies and entries sequentially despite having batched queries available earlier.
**Action:** Always verify that batched queries (like `findBySkusAndLocation`) are actually mapped and used inside subsequent loops rather than performing redundant individual queries. Ensure helper functions accept optional batched data to prevent duplicate database roundtrips.

## 2026-07-18 - Isolated HTTP Server Databases in Integration Tests
**Learning:** When using `php -S` to spawn background servers for local integration tests, the spawned process executes in a completely separate memory space. If `DB_CONNECTION=sqlite` and `DB_DATABASE=:memory:` are used, the test process and the background server process will each create their own, totally isolated in-memory SQLite databases, causing HTTP requests to fail when looking for data seeded by the test's `setUp` method. Additionally, if the test runner doesn't explicitly pass the `DB_*` environment variables down to the `php -S` sub-process, the background server will fall back to its defaults (e.g., trying to connect to a default `pgsql` database).
**Action:** When writing HTTP E2E integration tests that spawn a background `php -S` server, always explicitly export and pass the `DB_*` environment variables directly into the command string (e.g., `DB_CONNECTION={$dbConn} ... php -S ...`). When testing locally with SQLite, never use `:memory:`; instead, use a shared physical SQLite file (like `storage/data/test.sqlite`) so both processes can interact with the same database state.
## 2026-07-16 - N+1 Query in DemandForecaster bulk evaluation
**Learning:** In bulk reporting operations (like ), helper methods inside loops (like ) can trigger N+1 queries if they internally perform database lookups, even if the parent method pre-fetches the data into maps.
**Action:** When a method is called in a loop and performs database lookups, modify its signature to accept optional pre-fetched arrays (e.g. ). In the loop, pass the pre-fetched mapped data downward instead of relying on the method's internal fallback lookups.

## 2024-07-16 - N+1 Query in DemandForecaster bulk evaluation
**Learning:** In bulk reporting operations (like DemandForecaster::getDemandPlanningReport), helper methods inside loops (like calculateSalesVelocity) can trigger N+1 queries if they internally perform database lookups, even if the parent method pre-fetches the data into maps. Also, looping over arrays that have already been queried (e.g. findAll) to look up specific entities (e.g. findBySkuAndLocation) triggers N+1.
**Action:** When a method is called in a loop and performs database lookups, modify its signature to accept optional pre-fetched arrays (e.g. ?array $entries = null). In the loop, pass the pre-fetched mapped data downward instead of relying on the method's internal fallback lookups. For repositories returning maps, directly access array keys.













