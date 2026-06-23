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
