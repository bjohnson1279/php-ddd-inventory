## 2024-09-01 - Chained Array Functions in PHP Lead to Unnecessary Overhead
**Learning:** Combining multiple `array_filter` and `array_reduce` operations sequentially in PHP creates hidden O(N) traversals and allocates intermediate arrays in memory. In areas like demand forecasting that process many records, this causes measurable CPU and memory pressure.
**Action:** Replace chained functional array methods with a single, well-structured `foreach` loop to calculate multiple aggregates in exactly one pass without intermediate allocations.
