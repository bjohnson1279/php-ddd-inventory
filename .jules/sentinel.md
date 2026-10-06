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
## 2024-10-31 - ZPL Injection in Thermal Printer API
**Vulnerability:** The `/api/hardware/print-thermal` endpoint constructs a ZPL string directly from user inputs `labelType` and `barcodeValue` without sanitization. This introduces a ZPL injection vulnerability, allowing an attacker to inject arbitrary ZPL control characters to manipulate the printed label, bypass printer security, or cause denial of service.
**Learning:** Constructing ZPL commands from unsanitized input is analogous to XSS or command injection. Attackers can leverage control characters (`^` and `~`) to change printer configurations or alter the label's intent.
**Prevention:** Always sanitize user input prior to interpolation into ZPL strings by stripping out ZPL control characters, particularly `^` and `~`.

## 2024-05-24 - Fix IDOR in Compliance Controller
**Vulnerability:** IDOR in ComplianceController methods (`list`, `verify`, `reconstruct`, `replay`) trusting the `tenantId` query parameter from user input.
**Learning:** Controllers in the system must not blindly trust `tenantId` from `$request->query('tenantId')` or `$_GET['tenantId']` for data isolation.
**Prevention:** Always use the securely resolved `tenantId()` helper (or fallback to 'system') instead of user-provided tenant identifiers.

## Assertiveness, Decisive PR Execution & Comparative Recommendations
- **Proactive & Decisive PR Submission**: Do NOT repeatedly pause to ask permission to open a pull request (e.g., avoid "Would you like me to open a PR for this?", "Can I submit the PR?", "Should I go ahead and create the PR?"). When an optimization, fix, or feature satisfies requirements, passes local test runners/linters, and preserves architectural invariants, proceed decisively to create and submit the Pull Request with a clear, structured summary and rationale.
- **Explicit Recommendations on Options**: When presenting multiple implementation strategies or architecture options (e.g., Option A vs. Option B), NEVER leave the choice open-ended or passive. Always make an explicit, reasoned recommendation (prefixed with `(Recommended)`) based on **overall technical effectiveness**:
  1. *Algorithmic & Complexity Gains*: Time and space complexity impact (O(N*M) -> O(N+M), reduction of nested scans).
  2. *Resource Overhead*: Heap allocations, memory pressure, and GC pause reduction.
  3. *Domain & Architecture Invariants*: Strict backward compatibility, contract stability, and prevention of regression risks.
  4. *Security & Reliability*: Input validation, cryptographic safety, and concurrency safety.
- **Lead with Recommended Path**: State clearly why the recommended solution delivers the highest net value and immediately execute or propose it as the primary course of action rather than asking open-ended questions.

## Scope Verification, Minimal Churn & CI Protection Directives
- **Scope Verification Before Variable Binding**: When adding interactive states or accessibility attributes (e.g. `disabled={loading}`, `aria-busy={loading}`, `isSubmitting`), NEVER assume a variable identifier exists. Always inspect component props, local state hooks (`useState`), or declaration scope first. If not defined, declare the state hook or reuse an existing scope variable. Never introduce TS2304 / TS2552 ("Cannot find name") compile errors.
- **Surgical Edits Only (No Whole-File Formatting)**: Never run whole-file code formatters (Prettier, Black, Pint, rustfmt) across unmodified lines. Changes must be strictly range-scoped and limited to the minimal AST block needed. Avoid noisy quote/whitespace churn that masks real logic changes and causes merge conflicts. Verify with `git diff -w` that non-functional churn is zero.
- **Zero Scratch File Commits**: Never stage or commit ad-hoc verification, patch, or debug scripts (`test.cjs`, `fix_*.cjs`, `fix_*.php`, `patch_*.py`, `patch_*.sh`, `scratch_*`). Execute checks via the project's native test commands (`npm test`, `pytest`, `phpunit`, etc.) and delete temporary scripts before creating git commits.
- **Never Weaken CI Workflows**: Do not modify `.github/workflows/**` to bypass failures (e.g. adding `|| true`, setting `continue-on-error: true`, or commenting out assertions). Always resolve the defect in the source code or test fixture.
- **Explicit Parameter & Variable Types**: In TypeScript files, avoid implicit `any` by always providing explicit types on functions, parameters, and arrow callbacks (e.g. `(id: string) => ...`). Verify zero type errors with `tsc --noEmit` before committing.

## 2026-09-29 - Non-Destructive Security Patching & CI Protection
**Learning:** Security patches must never weaken CI workflow files (`.github/workflows/**`) by appending `|| true` or `continue-on-error: true` to suppress test/build failures. Furthermore, when adding defensive type assertions or input validators in TypeScript, omitting explicit types can introduce `TS7006: Parameter implicitly has an 'any' type`.
**Action:** Never modify CI workflow definitions to bypass test failures; resolve the underlying issue in source code or test fixtures. Always provide explicit types on newly introduced parameters and helper functions. Ensure zero scratch scripts (`fix_*.php`, `test_*.js`) are committed.

## Additive Documentation & Scratch Cleanliness Directives
- **Strictly Additive Journal Updates**: When updating `.jules/*.md`, strictly append new dated entries (`## YYYY-MM-DD - Title`). NEVER delete, truncate, or overwrite historical learnings or previous entries.
- **Substantive Code Diff Requirement**: Pull requests must include substantive code changes in `src/`, `app/`, `lib/`, or `tests/`. Never open PRs that modify only `.jules/*.md` journals or root scratch scripts.
- **Zero Scratch File Commits**: Never commit `*.diff`, `*.patch`, `test_*.ts`, `test_*.js`, `test.cjs`, `fix_*.php`, or `patch_*.py` files. Always remove temporary debugging or verification scripts prior to committing.

## Scope Quarantine, Journaling & Security Test Invariants
- **Strictly Append-Only Journaling**: When adding learnings to `.jules/*.md`, append strictly at the end of the file. Do not rewrite, deduplicate, or remove lines beginning with `## YYYY-MM-DD`.
- **Surgical Scope Quarantine**: Modify only the files directly involved in the issue and their corresponding test fixtures. Do not delete, rename, or perform drive-by cleanups of unrelated root-level scripts or legacy files.
- **Coupled Test Fixture Awareness for Security Invariants**: When changing fail-open fallback behavior (such as hardening decryption to fail closed), always update upstream test mocks that rely on plaintext credentials or mock values.

## 2026-10-06 - SQL Injection in Tenant Database DDL Provisioning
**Vulnerability:** Constructing DDL SQL statements (`CREATE DATABASE "$dbName"`, `DROP DATABASE IF EXISTS "$dbName"`, `pg_terminate_backend`) via dynamic variable interpolation allowed SQL injection in tenant database provisioning routines.
**Learning:** DDL queries (such as `CREATE DATABASE`) cannot be parameterized in PostgreSQL or standard PDO transactions. Unsanitized identifier variables injected into DDL string templates enable attackers to inject arbitrary SQL statements.
**Prevention:** Enforce strict alphanumeric regex validation (`/^[a-zA-Z0-9_]+$/`) on database identifier names before constructing DDL queries, escape double-quote identifier characters (`str_replace('"', '""', $dbName)`), and use parameterized queries for non-DDL queries like `pg_terminate_backend`.
