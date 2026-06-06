## 2026-06-05 - Webhook HMAC Validation Bypass
**Vulnerability:** Shopify webhook signature validation was skipped if the SHOPIFY_WEBHOOK_SECRET environment variable was missing or empty, incorrectly returning a 200/401 based on downstream errors rather than a 500 error, allowing unauthenticated payloads to bypass HMAC checks.
**Learning:** If an environment secret meant for signature validation is optional or empty, the application must explicitly fail securely (e.g., HTTP 500) rather than quietly skipping the verification and processing the payload.
**Prevention:** Always implement a strict existence and non-empty check for required cryptographic secrets before attempting signature validation, returning an explicit error if unconfigured.

## 2024-05-24 - Upgrade password hashing algorithm
**Vulnerability:** The application was using the older `bcrypt` algorithm for password hashing via `PASSWORD_BCRYPT`.
**Learning:** Bcrypt is susceptible to specialized hardware attacks (GPUs/ASICs). Argon2id is the current recommended algorithm.
**Prevention:** Always use `PASSWORD_ARGON2ID` instead of `PASSWORD_BCRYPT` or `PASSWORD_DEFAULT` when creating new password hashes in PHP to ensure resistance against modern hardware-accelerated cracking attacks.
