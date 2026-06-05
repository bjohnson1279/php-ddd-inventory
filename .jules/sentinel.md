## 2026-06-05 - Webhook HMAC Validation Bypass
**Vulnerability:** Shopify webhook signature validation was skipped if the SHOPIFY_WEBHOOK_SECRET environment variable was missing or empty, incorrectly returning a 200/401 based on downstream errors rather than a 500 error, allowing unauthenticated payloads to bypass HMAC checks.
**Learning:** If an environment secret meant for signature validation is optional or empty, the application must explicitly fail securely (e.g., HTTP 500) rather than quietly skipping the verification and processing the payload.
**Prevention:** Always implement a strict existence and non-empty check for required cryptographic secrets before attempting signature validation, returning an explicit error if unconfigured.
