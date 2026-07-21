## 2024-05-24 - DoS Risk via Unbounded External API Calls
**Vulnerability:** External HTTP requests via cURL to NetSuite, Shopify, and Xero were lacking `CURLOPT_TIMEOUT` and `CURLOPT_CONNECTTIMEOUT` definitions.
**Learning:** Default PHP cURL configurations can block indefinitely (or for system-level timeouts) if an external service stops responding, leading to thread exhaustion and complete application denial-of-service (DoS).
**Prevention:** Always mandate explicit connection and execution timeouts (e.g., 10s connection, 30s timeout) on all outbound network boundaries.
