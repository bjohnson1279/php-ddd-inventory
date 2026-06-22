## 2024-06-22 - Static caching in event listeners
**Learning:** Batch processing can cause an N+1 query problem if event listeners fetch the same product entity repeatedly from the database for each individual event.
**Action:** Use a static array cache within synchronous listeners to temporarily memoize database reads during batch execution, significantly improving performance.
## 2024-06-22 - Avoid N+1 queries in synchronous listeners via static caching
**Learning:** Bulk operations that dispatch multiple events trigger listeners repeatedly, causing N+1 queries. We can't always depend on the entity layer to fix this if the events run synchronously.
**Action:** Use a static array cache paired with `beginBatch()` and `endBatch()` methods in the listener to pre-load queries (using chunked `whereIn` clauses). Invoke the batch wrapper directly from the use cases (in a try/finally block) to avoid unbounded static cache memory leaks in queue workers.
## 2024-06-22 - Exception handling in static preloads
**Learning:** Silently catching database exceptions during cache preloading in a queued listener causes permanent data inconsistency by making the worker skip critical logic and mark the job as successful.
**Action:** When wrapping bulk database queries in `try/catch` for testing environments, ensure the fallback strictly checks for isolated test scenarios (like `sqlite` driver and 'no such table' messages) and re-throws the exception otherwise.
