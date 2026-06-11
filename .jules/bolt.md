## 2026-06-11 - Batched Eloquent Query
**Learning:** Resolving N+1 query performance issues in webhook or API controllers processing multiple line items can be done by fetching all related entities upfront using batched repository methods like `findBySkus`, processing them in memory, and persisting via `saveAll`, rather than calling single-item UseCases in a loop.
**Action:** Always verify the codebase state first, and when implementing batching, retrieve and save collections in memory to reduce database roundtrips.
