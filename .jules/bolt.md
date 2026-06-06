## 2026-06-06 - Batch Saving Only Affected Entities
**Learning:** In services where a large collection of entities (like active cost layers) is retrieved but only a few are modified, calling a bulk `saveBatch` with the entire collection causes unnecessary overhead (e.g. `saveBatch` items count is 10000 instead of 1).
**Action:** Return an array of modified/affected entities from processing loops and only pass those specific entities to the repository's `saveBatch` method to avoid redundant database operations.
