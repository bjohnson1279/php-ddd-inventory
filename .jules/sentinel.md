## 2024-05-18 - Fix Authorization Bypass in Setup Endpoint
**Vulnerability:** The `/api/setup` endpoint allowed anyone to create an `admin` account in an *existing* tenant's organization because it relied on `insertOrIgnore` for the tenant record but continued executing user creation logic regardless.
**Learning:** `insertOrIgnore` swallows unique constraint violations silently. If subsequent logic depends on the creation of that specific record to establish boundaries (like creating an initial admin user for a new tenant), using it allows attackers to piggyback onto existing records to escalate privileges or bypass authorization.
**Prevention:** Always explicitly check for the existence of boundary/container records (like tenants or organizations) before creating initial administrative users. Do not rely on ignored constraint errors to handle existence checks if subsequent logic must only run for newly created boundaries.

## 2026-06-22 - Prevent IDOR in AssignRoleToUser
**Vulnerability:** IDOR (Insecure Direct Object Reference) in `AssignRoleToUser` where a malicious admin from one tenant could assign roles to a user in another tenant because there was no cross-tenant isolation check between the acting user and target user.
**Learning:** Even if the actor's permissions are verified (`canDo('users:manage')`), we must ensure the actor's tenant ID matches the target entity's tenant ID to enforce true multi-tenancy boundaries.
**Prevention:** Always verify that `$actor->getTenantId()->getValue() === $target->getTenantId()->getValue()` before allowing cross-user modifications.

## 2024-05-18 - Fix Information Leakage in API Error Responses
**Vulnerability:** Information leakage via generic exception messages. 18 API routes in `public/index.php` were catching raw `\Exception` objects and echoing `$e->getMessage()` with a 400 status directly to clients, bypassing safe error formatting.
**Learning:** Returning `$e->getMessage()` unconditionally directly exposes backend system internals (stack traces, SQL errors, logic paths) to users, enabling reconnaissance.
**Prevention:** Always restrict error exposition to known-safe domains (e.g. `\InvalidArgumentException` or custom `\ValidationException`). Use `error_log()` for unexpected exceptions and return a generic `500` status with a sanitized message.

## 2026-06-25 - TimescaleDB Setup and Database Parity Constraints
**Learning:** In multi-variant backends (GraphQL, Express, Laravel), switching database engines (e.g., reverting the Express backend to SQLite or using mock local SQLite files) breaks TimescaleDB hypertable features and causes database drift. Additionally, database connection configuration must be securely validated.
**Action:** 
- Maintain database engine parity across all service variants by strictly using PostgreSQL for physical datastores.
- Do not run `prisma db push` during automated npm package installation (`postinstall`) in CI or production build environments, as it will fail due to the absence of a running database. Limit postinstall steps to `prisma generate` and execute migrations/pushes in dedicated pipeline steps or deployment startup phases.
- Ensure that any dynamic database connection strings (like `DATABASE_URL` built from separate components) are validated on server startup and fallback safely to trusted local defaults for development environments.
- Protect raw SQL queries used to enable the `timescaledb` extension or initialize hypertables from SQL injection vulnerabilities by using parameterized queries or strict schema names.
