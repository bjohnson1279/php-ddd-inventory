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

## 2024-05-24 - [Fix information disclosure in controllers]
**Vulnerability:** Generic exception messages leaking internal information to the client via 400 API responses.
**Learning:** A number of controllers were using catch blocks that threw `$e->getMessage()` for generic `Exception` objects directly to clients. This could expose stack traces, DB queries, or internal logic.
**Prevention:** Explicitly catch expected domain exceptions (e.g. `\InvalidArgumentException`, `ValidationException`) and safely pass their messages to the client. For generic unexpected exceptions, catch them, log the original error message with `error_log()`, and return a generic "An internal server error occurred." response.
## 2024-05-24 - [Fix Information Leakage in Controller Catch Blocks]
**Vulnerability:** Controller classes in `src/Infrastructure/Http/Controllers/` caught generic `Exception` objects and directly exposed their `$e->getMessage()` payload back to clients in a 400 Bad Request response. This exposes backend internals and database exceptions.
**Learning:** Returning `$e->getMessage()` unconditionally directly exposes backend system internals (stack traces, SQL errors, logic paths) to users, enabling reconnaissance. When patching this, be mindful that simply changing `catch (Exception $e)` to specific exception types may break existing unit tests that expect `Exception` to be caught by the general catch block, thus failing the test due to 500 error instead of 400 or 404, because the underlying service actually throws `Exception` (or unit tests mock generic `Exception`).
**Prevention:** Instead of replacing the `catch` block completely and breaking the structure (and thus test assertions and status codes), it is safer to inject an `if` check inside the existing `catch (Exception $e)` block that validates if `$e` is an instance of a safe exception (`InvalidArgumentException`, `ValidationException`, `DomainException`). If not safe, log it and return 500. This preserves the original logic flow for safe exceptions while protecting against leaks for generic ones.
**Vulnerability:** Controller classes in src/Infrastructure/Http/Controllers/ caught generic Exception objects and directly exposed their $e->getMessage() payload back to clients in a 400 Bad Request response. This exposes backend internals and database exceptions.
**Learning:** Returning $e->getMessage() unconditionally directly exposes backend system internals (stack traces, SQL errors, logic paths) to users, enabling reconnaissance. When patching this, be mindful that simply changing catch (Exception $e) to specific exception types may break existing unit tests that expect Exception to be caught by the general catch block, thus failing the test due to 500 error instead of 400 or 404, because the underlying service actually throws Exception (or unit tests mock generic Exception).
**Prevention:** Instead of replacing the catch block completely and breaking the structure (and thus test assertions and status codes), it is safer to inject an if check inside the existing catch (Exception $e) block that validates if $e is an instance of a safe exception (InvalidArgumentException, ValidationException, DomainException). If not safe, log it and return 500. This preserves the original logic flow for safe exceptions while protecting against leaks for generic ones.

## 2024-05-24 - Hardcoded database password fallback
**Vulnerability:** A hardcoded database password fallback ('secret') was used if the DB_PASSWORD environment variable was not found, which exposes the DB to an unauthorized access risk if not configured in the host environment.
**Learning:** Default values used with getenv() fallback configurations can inadvertently introduce hardcoded credentials into production environments if variables are missing.
**Prevention:** Avoid hardcoding default credentials. When falling back to a string for configuration, use an empty string or explicitly throw an error if the credential must exist, instead of supplying a potentially guessable fallback like 'secret'. Furthermore, correctly replace ?: operators with strict comparisons like `getenv('DB_PASSWORD') !== false ? getenv('DB_PASSWORD') : ''` to avoid treating a valid string '0' as false.

## 2025-03-24 - SQL Injection Risk via Raw JSON Querying
**Vulnerability:** Raw SQL strings were used in Eloquent `whereRaw` and `selectRaw` clauses to extract JSON column properties (e.g., `whereRaw("metadata->>'locationId' = ?", [$id])`).
**Learning:** While parameterized raw queries prevent basic SQL injection, explicitly mapping complex JSON paths via string concatenation or raw drivers often trips static analysis tools and might mask engine-specific JSON parsing bugs.
**Prevention:** Always use Eloquent's built-in, engine-agnostic JSON querying path syntax (e.g., `->where('metadata->locationId', $id)`) instead of raw SQL snippets, which safely abstracts the database layer differences and satisfies SAST tools.

## 2025-03-24 - Hardcoded Password Fallback in DB Connection
**Vulnerability:** The database connection configuration files used the Elvis operator (`?:`) to fall back to a hardcoded password `'secret'` if `getenv('DB_PASSWORD')` was empty or evaluated to false.
**Learning:** In PHP, if an environment variable is set to `"0"` (string zero), `getenv()` returns `"0"`, which evaluates to `false` in a loose boolean context, triggering the fallback. This could lead to a production database unintentionally using a weak default password if misconfigured.
**Prevention:** Always use strict comparison (`!== false`) when reading critical environment variables via `getenv()` and never fall back to guessable defaults for sensitive credentials.

## 2024-07-24 - Missing Authorization Check on Report Endpoints
**Vulnerability:** Missing authorization check on `/api/reports/valuation`, `/api/reports/recall/{lotNumber}`, `/api/journal/entries`, `/api/returns/quarantine`, and `/api/returns/rma`. They required authentication but lacked RBAC checks via `$actor->canDo()`.
**Learning:** Endpoints may require authentication via `requireAuth()` but fail to implement fine-grained role-based access control, allowing any authenticated user (e.g., lower-privileged staff) to access sensitive reporting and accounting data.
**Prevention:** Always verify that not only is the user authenticated, but that they possess the specific permissions (`reports:view`, `inventory:read`) required to perform the action or view the data.

## 2024-05-18 - Prevented Information Leakage in API Controllers
**Vulnerability:** System exceptions and underlying internal errors were being returned directly in 500 HTTP responses via `$e->getMessage()`, potentially exposing database schema, file paths, or implementation details.
**Learning:** Catch blocks must be carefully reviewed to ensure they don't propagate raw exception messages for unhandled system errors (500s). While 400 responses can include validation details, 500s should always fall back to a generic message to prevent reconnaissance.
**Prevention:** Always log the full exception internally (e.g., using `error_log`) but return a sanitized, generic error response (e.g., `"An internal server error occurred."`) for 500 Internal Server Errors.

## 2024-05-24 - JSON Column SQL Injection Prevention
**Vulnerability:** SQL Injection via raw queries against JSON columns
**Learning:** Using `whereRaw("metadata->>'key' = ?")` bypassing Eloquent's bindings can lead to SQL injection or static analysis failures.
**Prevention:** Always use Eloquent's built-in JSON query syntax (e.g., `where('metadata->key', $value)`) to ensure automatic parameterization and cross-database compatibility.
## 2026-07-13 - Secure Rate Limit Proxy Handling
**Vulnerability:** Rate limit bypass via IP Spoofing (X-Forwarded-For).
**Learning:** The rate limiter blindly trusted the `HTTP_X_FORWARDED_FOR` header or failed to properly walk the proxy chain from right to left to establish the true client IP against a list of trusted proxies.
**Prevention:** Always validate `REMOTE_ADDR` against a trusted proxy list before parsing `HTTP_X_FORWARDED_FOR`, and traverse the list right-to-left to safely extract the untrusted client IP.
## 2024-05-24 - DoS Risk via Unbounded External API Calls
**Vulnerability:** External HTTP requests via cURL to NetSuite, Shopify, and Xero were lacking `CURLOPT_TIMEOUT` and `CURLOPT_CONNECTTIMEOUT` definitions.
**Learning:** Default PHP cURL configurations can block indefinitely (or for system-level timeouts) if an external service stops responding, leading to thread exhaustion and complete application denial-of-service (DoS).
**Prevention:** Always mandate explicit connection and execution timeouts (e.g., 10s connection, 30s timeout) on all outbound network boundaries.
