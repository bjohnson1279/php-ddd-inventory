## 2024-05-20 - Fix N+1 in RfidBulkScanWorker
**Learning:** Pre-fetching relationships manually via chunked IN queries avoids massive N+1 issues in batch processing, but must respect parameter limits of underlying databases (e.g. SQLite max 999 parameters, Postgres 65535). Use `array_chunk` on lists of IDs when pre-fetching to prevent DB driver crashes on large bulk payloads.
**Action:** Always wrap large bulk IN query operations (like `whereIn`) with `array_chunk` in repositories meant to handle bulk IoT or ingestion data.
