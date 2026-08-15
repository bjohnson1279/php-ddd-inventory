## 2024-05-19 - Concurrent cURL execution for Webhooks
**Learning:** Using `curl_multi` is a powerful way to offload blocking HTTP I/O when processing batches of outbound requests, significantly reducing overall execution time compared to a sequential loop of `curl_exec` calls. When managing HTTP errors with `curl_multi`, it's critical to retrieve the response payload using `curl_multi_getcontent` before closing the handle.
**Action:** When implementing concurrent HTTP requests with `curl_multi` in PHP, always ensure that response data is explicitly fetched via `curl_multi_getcontent` if it needs to be included in exception messages or logging. Additionally, remember that standard PHP Exceptions cannot be cloned; assign them directly when capturing errors inside a loop for delayed processing.

## 2024-08-11 - Instance Caching Over Static Caching
**Learning:** Using `static` variables inside methods to cache data eliminates N+1 queries but can introduce test bleed in PHPUnit and serve stale data in long-running processes (like Laravel Octane).
**Action:** Always prefer instance-level properties (e.g., `private ?array $cache`) over method-level `static` variables for caching state during object lifecycles in this application architecture.
