## 2024-05-19 - Concurrent cURL execution for Webhooks
**Learning:** Using `curl_multi` is a powerful way to offload blocking HTTP I/O when processing batches of outbound requests, significantly reducing overall execution time compared to a sequential loop of `curl_exec` calls. When managing HTTP errors with `curl_multi`, it's critical to retrieve the response payload using `curl_multi_getcontent` before closing the handle.
**Action:** When implementing concurrent HTTP requests with `curl_multi` in PHP, always ensure that response data is explicitly fetched via `curl_multi_getcontent` if it needs to be included in exception messages or logging. Additionally, remember that standard PHP Exceptions cannot be cloned; assign them directly when capturing errors inside a loop for delayed processing.

## 2024-08-11 - Instance Caching Over Static Caching
**Learning:** Using `static` variables inside methods to cache data eliminates N+1 queries but can introduce test bleed in PHPUnit and serve stale data in long-running processes (like Laravel Octane).
**Action:** Always prefer instance-level properties (e.g., `private ?array $cache`) over method-level `static` variables for caching state during object lifecycles in this application architecture.

## 2026-08-12 - Copy-on-Write Sorting Overhead
**Learning:** In PHP, `usort()` operates in-place. Because arrays in PHP use copy-on-write, passing an array to a method that performs `usort()` on it implicitly triggers an $O(N)$ copy of the array *before* the $O(N \log N)$ sort begins. In multi-method valuation algorithms (like FIFO + LIFO), sorting the same array inside multiple helper functions causes massive duplicated overhead for both CPU and memory.
**Action:** When a dataset needs to be evaluated in multiple sorted orders, sort it exactly once at the controller/caller level and use $O(N)$ operations like `array_reverse()` to pass variations to helper methods, preventing implicit array cloning.
## 2026-08-31 - mapWithKeys over keyBy
**Learning:** When generating keyed hash maps from Eloquent/Database collections, `->get()->keyBy('id')` will inherently rely on PHP's internal array mechanisms to dictate key types. Using `->get()->mapWithKeys(fn($item) => [(string)$item->id => $item])` forces string casting, and is significantly faster, reducing overhead.
**Action:** Use `->get()->mapWithKeys` instead of `->keyBy` when performance is critical, and explicit type stability of keys is important.
## 2024-09-01 - Chained Array Functions in PHP Lead to Unnecessary Overhead
**Learning:** Combining multiple `array_filter` and `array_reduce` operations sequentially in PHP creates hidden O(N) traversals and allocates intermediate arrays in memory. In areas like demand forecasting that process many records, this causes measurable CPU and memory pressure.
**Action:** Replace chained functional array methods with a single, well-structured `foreach` loop to calculate multiple aggregates in exactly one pass without intermediate allocations.
origin/master
