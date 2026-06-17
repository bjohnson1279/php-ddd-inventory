## 2026-06-17 - Insecure Pseudo-UUID Generation
**Vulnerability:** Deterministic pseudo-UUIDs generated using the cryptographically weak MD5 hashing algorithm.
**Learning:** Legacy systems often attempt to coerce non-UUID string identifiers into UUID-like formats using insecure hashes, which exposes the system to potential collision attacks. MD5's fast computation and proven vulnerabilities make it unsuitable for generating deterministic identifiers where uniqueness is critical to avoid data overwrites or unauthorized access.
**Prevention:** When coercing string IDs to a deterministic format, always use a cryptographically strong hash function like SHA-256 (e.g., `hash('sha256', $id)`) or implement a strict RFC 4122 compliant UUIDv5 generation.
