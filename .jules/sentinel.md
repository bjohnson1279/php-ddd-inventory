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
