## 2024-07-10 - Fix N+1 query saving disassembled kit components
**Learning:** Performing database queries and saves within a loop leads to significant N+1 performance bottlenecks.
**Action:** Extract entity IDs and pre-fetch them in bulk before the loop using `findByIds`. Accumulate modified entities in memory and persist them after the loop using batch methods like `saveBatch` and `saveAll`.
