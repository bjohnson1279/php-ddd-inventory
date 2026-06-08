## 2026-06-05 - Webhook HMAC Validation Bypass
**Vulnerability:** Shopify webhook signature validation was skipped if the SHOPIFY_WEBHOOK_SECRET environment variable was missing or empty, incorrectly returning a 200/401 based on downstream errors rather than a 500 error, allowing unauthenticated payloads to bypass HMAC checks.
**Learning:** If an environment secret meant for signature validation is optional or empty, the application must explicitly fail securely (e.g., HTTP 500) rather than quietly skipping the verification and processing the payload.
**Prevention:** Always implement a strict existence and non-empty check for required cryptographic secrets before attempting signature validation, returning an explicit error if unconfigured.

## 2024-05-24 - Upgrade password hashing algorithm
**Vulnerability:** The application was using the older `bcrypt` algorithm for password hashing via `PASSWORD_BCRYPT`.
**Learning:** Bcrypt is susceptible to specialized hardware attacks (GPUs/ASICs). Argon2id is the current recommended algorithm.
**Prevention:** Always use `PASSWORD_ARGON2ID` instead of `PASSWORD_BCRYPT` or `PASSWORD_DEFAULT` when creating new password hashes in PHP to ensure resistance against modern hardware-accelerated cracking attacks.

## 2026-06-08 - Empty Webhook Secret Validation Bypass
**Vulnerability:** The Shopify webhook signature verification process in `ShopifyWebhookVerifier` did not check if the configured `webhookSecret` was empty before calling `hash_hmac()`. If the secret was empty, `hash_hmac()` processed it without error, allowing attackers to sign arbitrary payloads using an empty string key, completely bypassing HMAC verification.
**Learning:** Functions like `hash_hmac` do not inherently fail or warn when provided with an empty key. They compute a technically valid HMAC for that empty key, leading to a critical bypass if the secret is unintentionally unconfigured.
**Prevention:** Always implement an explicit `empty()` check on shared secrets (like API keys or HMAC secrets) before using them in cryptographic signing or verification functions to prevent null-key spoofing attacks.
