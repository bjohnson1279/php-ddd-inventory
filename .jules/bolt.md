## 2026-06-17 - Prevent database parameter limits on `whereIn` queries
**Learning:** `whereIn` arrays that scale with user data (like all products in a tenant) can easily exceed database parameter limits (e.g., PostgreSQL limit).
**Action:** Always wrap large or unbounded `whereIn` arrays with `array_chunk(..., 500)` and merge the results.
