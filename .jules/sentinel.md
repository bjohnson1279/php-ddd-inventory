## 2024-07-30 - Fallback Secret Exposure
**Vulnerability:** The ComplianceLedgerService hardcoded a fallback private key if the `APP_ENV` environment variable was not set, allowing production environments to default to a known weak key if misconfigured.
**Learning:** Security configurations must fail closed. When handling environment-specific security settings like private keys, the application should throw an exception if the required variables are missing, rather than silently falling back to a hardcoded testing key unless explicitly running in a verified 'testing' environment.
**Prevention:** Always use strict environment checks (e.g., `getenv('APP_ENV') === 'testing'`) before allowing fallback logic. Default to throwing `\RuntimeException` for missing security configurations in all other cases.

## Prevention Directives for Automated Refactoring
- **Never Overwrite Complete Files**: Always use range-scoped replacement chunks (`StartLine`/`EndLine`) for edits to `schema.prisma`, `index.ts`, `public/index.php`, or DDL SQL scripts.
- **Do Not Remove Core Declarations**: Do not delete existing route registrations or database DDL tables.
- **Environment Isolation Compatibility**: When replacing fallback secrets, preserve test environment execution via `!getenv('APP_ENV')` or `getenv('APP_ENV') === 'testing'`.
- **No Scratch Files**: Never stage or commit `test_*.ts`, `test_*.js`, or `test.js` files to git.


## 2024-07-31 - Insecure Deserialization in Queue Worker
**Vulnerability:** The queue worker deserialized raw database event payloads using `unserialize()` without restricting the allowed classes. This allowed an attacker with database access to inject malicious serialized objects and achieve Remote Code Execution (RCE).
**Learning:** Even when reading from an internal database, serialized data must be treated as untrusted input. When mitigating insecure deserialization with `unserialize()` by utilizing the `allowed_classes` option, avoid injecting massive, hardcoded class arrays directly inline. Instead, extract the whitelist to a central configuration/helper class (e.g., `AllowedClasses::get()`) to maintain readability and reduce brittleness.
**Prevention:** Always pass an explicit array of `allowed_classes` to `unserialize()`, or better yet, migrate to a safer serialization format like JSON for queue payloads.
## 2024-05-20 - Database Password Fallback Exposure
**Vulnerability:** A hardcoded fallback password ('secret') was used for database connections if the `DB_PASSWORD` environment variable was not set, risking exposure in unconfigured production environments.
**Learning:** Security configurations must fail closed. When handling environment-specific security settings like database passwords, the application should throw an exception if the required variables are missing.
**Prevention:** Remove hardcoded fallbacks and use a strict `throw new \RuntimeException` when critical security environment variables are missing. Ensure to clean up any `.orig` or `.rej` files generated during patching to avoid exposing source code.
## 2026-08-12 - Insecure ComplianceLedgerService Fallback Key
**Vulnerability:** The ComplianceLedgerService hardcoded a fallback private key if the `APP_ENV` environment variable was not set, allowing production environments to default to a known weak key if misconfigured.
**Learning:** Incomplete validation of the environment (`!$env`) allowed insecure defaults to activate in production scenarios without a defined environment.
**Prevention:** Always strictly enforce secure fallback logic only in testing (`getenv('APP_ENV\) === 'testing'`). Default to throwing exceptions for missing security credentials across all other environments.
## 2024-08-01 - DB_PASSWORD Fallback Exposure
**Vulnerability:** A hardcoded fallback password ('postgres') was used for database connections across multiple persistence configuration files if the `DB_PASSWORD` environment variable was not set, risking exposure in unconfigured production environments.
**Learning:** Security configurations must fail closed. When handling environment-specific security settings like database passwords, the application should throw an exception if the required variables are missing, unless explicitly running in a verified 'testing' environment.
**Prevention:** Always use strict environment checks (e.g., `getenv('APP_ENV') === 'testing'`) before allowing fallback logic. Default to throwing `\RuntimeException` for missing security configurations in all other cases. Ensure duplicate configuration keys are cleaned up.

## Prevention Directives for Automated Refactoring
- **Never Overwrite Complete Files**: Always use range-scoped replacement chunks (`StartLine`/`EndLine`) for edits to `schema.prisma`, `index.ts`, `public/index.php`, or DDL SQL scripts.
- **Do Not Remove Core Declarations**: Do not delete existing route registrations or database DDL tables.
- **Environment Isolation Compatibility**: When replacing fallback secrets, preserve test environment execution via `!getenv('APP_ENV')` or `getenv('APP_ENV') === 'testing'`.
- **No Scratch Files**: Never stage or commit `test_*.ts`, `test_*.js`, `test.cjs`, `fix_*.php`, or `test.js` files to git.
- **No Unresolved Conflict Markers**: Never stage or commit files containing Git merge conflict markers (`<<<<<<<`, `=======`, `>>>>>>>`, `|||||||`). Always resolve conflicts cleanly before committing.

## Hallucinatory Task & Empty PR Directives
- **Zero-Diff Task Termination**: If the requested optimization, refactor, or fix is ALREADY natively present in the target branch, DO NOT create an empty pull request or commit an acknowledgment PR. Exit the task cleanly without opening a PR.
- **Stale Suggestion Guard**: Always verify the current code on `main`/`master` before planning changes. If no actionable diff is required, cancel task execution immediately.

## 2026-09-10 - SQL Injection in PostgreSQL SET Statement
**Vulnerability:** The `requireAuth` helper function interpolated the user-controlled `tenant_id` from the API token directly into a PostgreSQL `SET app.current_tenant_id = '...'` query, creating a critical SQL injection vulnerability.
**Learning:** The PostgreSQL `SET` command does not natively support parameter binding via PDO. Using string interpolation to set session-level variables is inherently unsafe when the value originates from user input.
**Prevention:** When setting session-level configuration variables in PostgreSQL via Eloquent or raw statements, always use the `set_config` function with parameterized bindings (e.g., `SELECT set_config('app.current_tenant_id', ?, false)`) to ensure safe execution.
## 2024-10-27 - Critical SQL Injection in Queue Worker via SET app.current_tenant_id
**Vulnerability:** The queue worker script (`scripts/queue-worker.php`) interpolated the `tenant_id` from incoming queue jobs directly into a PostgreSQL `SET app.current_tenant_id = '...'` query. Because `SET` does not support parameter binding in PDO, this created a critical SQL injection vulnerability if a job payload was manipulated.
**Learning:** Even internal backend processes like queue workers that handle seemingly internal data (like tenant IDs) must use parameterized queries. The assumption that internal identifiers are safe from injection is a dangerous anti-pattern.
**Prevention:** When setting session-level configuration variables in PostgreSQL via Eloquent or raw statements, always use the `set_config` function with parameterized bindings (e.g., `SELECT set_config('app.current_tenant_id', ?, false)`) to ensure safe execution, rather than string interpolation with `SET`.

## Prevention Directives for Automated Refactoring
- **Never Overwrite Complete Files**: Always use range-scoped replacement chunks for edits to `schema.prisma`, `index.ts`, `public/index.php`, `db/schema.rb`, or DDL SQL scripts.
- **Do Not Remove Core Declarations**: Do not delete existing route registrations or database DDL tables.
- **Environment Isolation Compatibility**: When replacing fallback secrets, preserve test environment execution via `!getenv('APP_ENV')` or `getenv('APP_ENV') === 'testing'`.
- **No Scratch Files**: Never stage or commit `test_*.ts`, `test_*.js`, `test.cjs`, `fix_*.php`, or `test.js` files to git.
- **No Unresolved Conflict Markers**: Never stage or commit files containing Git merge conflict markers (`<<<<<<<`, `=======`, `>>>>>>>`, `|||||||`). Always resolve conflicts cleanly before committing.

## Completeness & Verification Directives
- **Explicit Parameter & Contract Validation**: When creating or modifying API endpoints (Express, Fastify, Rails, Laravel), always implement explicit parameter and request body validation schemas (e.g. `z.string().uuid()`) to prevent unhandled 404/500 fallthroughs.
- **Database Indexing for Queries**: When addressing query bottlenecks or adding query lookup filters, always implement native database index migrations rather than loading collections into memory and performing array filtering (`.filter()`, `.select`).
- **Co-Occurring Dependency Auditing**: When bumping any dependency version, verify that other transitive dependencies do not carry high/critical security advisories (e.g. run `bundler-audit`, `npm audit`). Never introduce a version bump that breaks underlying framework APIs.
- **Self-Verification Before Commit**: Always run syntax checks (`bash -n` for shell scripts, `tsc --noEmit` for TypeScript, linter checks) and targeted test runners locally before opening or updating a PR.

## Hallucinatory Task & Empty PR Directives
- **Zero-Diff Task Termination**: If the requested optimization, refactor, or fix is ALREADY natively present in the target branch, DO NOT create an empty pull request or commit an acknowledgment PR. Exit the task cleanly without opening a PR.
- **Stale Suggestion Guard**: Always verify the current code on `main`/`master` before planning changes. If no actionable diff is required, cancel task execution immediately.
## 2024-05-18 - SSRF Vulnerability in Dead Webhook Worker Code
**Vulnerability:** A duplicate, unreachable block of webhook delivery code in scripts/webhook-worker.php lacked the SSRF protections present in the active WebhookDeliveryWorker.php.
**Learning:** Duplicate code, even when currently unreachable (dead code), presents a significant security risk if it lacks necessary protections, as it can be easily reactivated or used as a reference for future implementations.
**Prevention:** Always remove dead code and ensure that security protections (like SSRF validation) are implemented in a central, reusable component rather than duplicated across scripts.

## 2026-10-01 - Redundant Vulnerable Webhook Worker Logic
**Vulnerability:** The standalone script `scripts/webhook-worker.php` duplicated the webhook delivery loop, but the duplicated loop bypassed the SSRF protections implemented in the `WebhookDeliveryWorker` class.
**Learning:** Legacy procedural scripts sometimes retain old code even after a new secure object-oriented component (like `WebhookDeliveryWorker`) is integrated. When a secure class is instantiated at the top of a script, any trailing duplicated procedural logic is not just dead code—it's a latent vulnerability, especially if the script execution flow can bypass the secure class or execute both.
**Prevention:** When patching vulnerabilities in legacy procedural scripts, first verify if a secure class component is already being instantiated. If so, simply delete the redundant, vulnerable procedural code instead of attempting to backport security fixes into it.
## 2024-10-31 - IDOR in AI Endpoints
**Vulnerability:** The `/api/anomaly-detection/analyze` and `/api/rebalance/matrix` endpoints in `public/index.php` were passing the untrusted `$_GET` global array directly into service methods (`analyze` and `getMatrix`) which expect a `tenantId` string. This allows for an Insecure Direct Object Reference (IDOR) bypass, where a malicious actor could theoretically manipulate the input to access other tenants' data.
**Learning:** Never pass raw globals like `$_GET` directly into service methods, especially those responsible for fetching data based on a tenant ID. Always resolve the tenant context securely from the authenticated session.
**Prevention:** Always use the securely resolved `tenantId()` helper function to fetch the current tenant ID, ensuring that AI endpoints and other services only access data for the currently authenticated tenant.

## 2026-10-15 - ZPL Injection Vulnerability in Thermal Printing Endpoints
**Vulnerability:** The `/api/shipping/label` and `/api/hardware/print-thermal` endpoints in `public/index.php` were constructing ZPL strings using unsanitized user inputs (`recipientName`, `labelType`, `barcodeValue`). This created a ZPL Injection vulnerability where a malicious actor could inject ZPL control characters (`^`, `~`) to execute unauthorized printer commands, alter labels, or cause Denial of Service (DoS) on connected thermal printers.
**Learning:** Constructing printer commands (like ZPL) by directly interpolating untrusted user input is analogous to SQL injection or XSS. ZPL relies heavily on specific control characters, and failing to sanitize inputs allows for command injection at the hardware level.
**Prevention:** Always sanitize any user input intended for ZPL string interpolation by explicitly stripping out ZPL control characters, specifically `^` and `~`.
