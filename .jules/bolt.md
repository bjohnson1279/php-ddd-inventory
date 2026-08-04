## 2026-08-04 - Resolve N+1 chunking overhead in SyncStockToShopify
**Learning:** Chunking array payloads for `whereIn` queries inside iterative loops introduces severe PHP overhead compared to executing a single query, provided the array payload remains under database engine limits (like SQLite's 32k variable limit in newer versions).
**Action:** When resolving N+1 database queries that map collections in batch processes, prefer single `whereIn` operations over `array_chunk` iteration loops unless specific framework constraints or database parameter limits dictate otherwise.
