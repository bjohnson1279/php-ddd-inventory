## 2026-06-06 - Batch Saving Only Affected Entities
**Learning:** In services where a large collection of entities (like active cost layers) is retrieved but only a few are modified, calling a bulk saveBatch with the entire collection causes unnecessary overhead (e.g. saveBatch items count is 10000 instead of 1).
**Action:** Return an array of modified/affected entities from processing loops and only pass those specific entities to the repository's saveBatch method to avoid redundant database operations.
## 2026-06-10 - Conditional Upsert for Bulk Location Saves
**Learning:** SQLite's handling of composite primary keys often lacks the implicit unique constraints necessary for upsert() to work cleanly in testing environments, while Postgres correctly supports it in production.
**Action:** When optimizing bulk insertions in Eloquent using upsert(), always include a driver check (e.g., checking if the connection driver is sqlite) to fallback to an iterative loop (like updateOrCreate) for SQLite environments. This ensures production queries are fast while keeping the in-memory SQLite test suite passing.
## 2026-06-11 - Batched Eloquent Query
**Learning:** Resolving N+1 query performance issues in webhook or API controllers processing multiple line items can be done by fetching all related entities upfront using batched repository methods like findBySkus, processing them in memory, and persisting via saveAll, rather than calling single-item UseCases in a loop.
**Action:** Always verify the codebase state first, and when implementing batching, retrieve and save collections in memory to reduce database roundtrips.
