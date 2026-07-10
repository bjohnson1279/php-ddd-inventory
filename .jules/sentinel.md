## 2025-03-24 - SQL Injection Risk via Raw JSON Querying
**Vulnerability:** Raw SQL strings were used in Eloquent `whereRaw` and `selectRaw` clauses to extract JSON column properties (e.g., `whereRaw("metadata->>'locationId' = ?", [$id])`).
**Learning:** While parameterized raw queries prevent basic SQL injection, explicitly mapping complex JSON paths via string concatenation or raw drivers often trips static analysis tools and might mask engine-specific JSON parsing bugs.
**Prevention:** Always use Eloquent's built-in, engine-agnostic JSON querying path syntax (e.g., `->where('metadata->locationId', $id)`) instead of raw SQL snippets, which safely abstracts the database layer differences and satisfies SAST tools.
