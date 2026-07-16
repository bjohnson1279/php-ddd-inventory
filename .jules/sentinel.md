## 2024-05-18 - Hardcoded Fallback Secret in Compliance Ledger
**Vulnerability:** A hardcoded fallback cryptographic secret (`compliance-fallback-secret-key-12345!@#`) was present in `ComplianceLedgerService`.
**Learning:** Hardcoded cryptographic materials severely compromise the integrity of security and compliance mechanisms, as they can be easily extracted from source control.
**Prevention:** Always enforce the explicit configuration of security keys via environment variables (e.g., throwing an exception when missing) rather than providing inherently insecure defaults.
