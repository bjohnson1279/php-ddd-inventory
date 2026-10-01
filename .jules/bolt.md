## 2024-05-19 - Concurrent cURL execution for Webhooks
**Learning:** Using `curl_multi` is a powerful way to offload blocking HTTP I/O when processing batches of outbound requests, significantly reducing overall execution time compared to a sequential loop of `curl_exec` calls. When managing HTTP errors with `curl_multi`, it's critical to retrieve the response payload using `curl_multi_getcontent` before closing the handle.
**Action:** When implementing concurrent HTTP requests with `curl_multi` in PHP, always ensure that response data is explicitly fetched via `curl_multi_getcontent` if it needs to be included in exception messages or logging. Additionally, remember that standard PHP Exceptions cannot be cloned; assign them directly when capturing errors inside a loop for delayed processing.

## 2024-08-11 - Instance Caching Over Static Caching
**Learning:** Using `static` variables inside methods to cache data eliminates N+1 queries but can introduce test bleed in PHPUnit and serve stale data in long-running processes (like Laravel Octane).
**Action:** Always prefer instance-level properties (e.g., `private ?array $cache`) over method-level `static` variables for caching state during object lifecycles in this application architecture.

## 2026-08-12 - Copy-on-Write Sorting Overhead
**Learning:** In PHP, `usort()` operates in-place. Because arrays in PHP use copy-on-write, passing an array to a method that performs `usort()` on it implicitly triggers an $O(N)$ copy of the array *before* the $O(N \log N)$ sort begins. In multi-method valuation algorithms (like FIFO + LIFO), sorting the same array inside multiple helper functions causes massive duplicated overhead for both CPU and memory.
**Action:** When a dataset needs to be evaluated in multiple sorted orders, sort it exactly once at the controller/caller level and use $O(N)$ operations like `array_reverse()` to pass variations to helper methods, preventing implicit array cloning.
## 2026-08-31 - mapWithKeys over keyBy
**Learning:** When generating keyed hash maps from Eloquent/Database collections, `->get()->keyBy('id')` will inherently rely on PHP's internal array mechanisms to dictate key types. Using `->get()->mapWithKeys(fn($item) => [(string)$item->id => $item])` forces string casting, and is significantly faster, reducing overhead.
**Action:** Use `->get()->mapWithKeys` instead of `->keyBy` when performance is critical, and explicit type stability of keys is important.
## 2024-09-01 - Chained Array Functions in PHP Lead to Unnecessary Overhead
**Learning:** Combining multiple `array_filter` and `array_reduce` operations sequentially in PHP creates hidden O(N) traversals and allocates intermediate arrays in memory. In areas like demand forecasting that process many records, this causes measurable CPU and memory pressure.
**Action:** Replace chained functional array methods with a single, well-structured `foreach` loop to calculate multiple aggregates in exactly one pass without intermediate allocations.
origin/master

## Prevention Directives for Automated Refactoring
- **Never Overwrite Complete Files**: Always use range-scoped replacement chunks (`StartLine`/`EndLine`) for edits to `schema.prisma`, `index.ts`, `public/index.php`, or DDL SQL scripts.
- **Do Not Remove Core Declarations**: Do not delete existing route registrations or database DDL tables.
- **Environment Isolation Compatibility**: When replacing fallback secrets, preserve test environment execution via `!getenv('APP_ENV')` or `getenv('APP_ENV') === 'testing'`.
- **No Scratch Files**: Never stage or commit `test_*.ts`, `test_*.js`, `test.cjs`, `fix_*.php`, or `test.js` files to git.
- **No Unresolved Conflict Markers**: Never stage or commit files containing Git merge conflict markers (`<<<<<<<`, `=======`, `>>>>>>>`, `|||||||`). Always resolve conflicts cleanly before committing.

## Hallucinatory Task & Empty PR Directives
- **Zero-Diff Task Termination**: If the requested optimization, refactor, or fix is ALREADY natively present in the target branch, DO NOT create an empty pull request or commit an acknowledgment PR. Exit the task cleanly without opening a PR.
- **Stale Suggestion Guard**: Always verify the current code on `main`/`master` before planning changes. If no actionable diff is required, cancel task execution immediately.

## 2024-09-07 - Eloquent bulk array hydration overhead
**Learning:** In Laravel Eloquent, using `->get()` and then iterating over the results to build a key-value array incurs an O(N) penalty due to instantiating intermediate stdClass models for every row. Using `->pluck('value_column', 'key_column')->toArray()` allows the query builder to construct the associative array directly from the database driver result set, bypassing object hydration.
**Action:** When aggregating or fetching simple key-value pairs (like SUM totals by ID) across large lists, always prefer `pluck()` over `get()`.

## 2024-09-10 - keyBy vs mapWithKeys Overhead
**Learning:** When generating keyed hash maps from Eloquent/Database collections, replacing `->keyBy('field')` with `->mapWithKeys(fn($item) => [(string)$item->field => $item])` is a de-optimization. `mapWithKeys` instantiates a new array for every single item and triggers an inner foreach loop. The fastest approach that avoids the `data_get` overhead of `keyBy('string')` while ensuring explicit string key casting is `->keyBy(fn($item) => (string)$item->field)`.
**Action:** Use `->keyBy(fn($item) => (string)$item->field)` instead of `mapWithKeys` when strict string casting and high performance are required on Eloquent collections.

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
## 2026-09-18 - Pluck with mapWithKeys Overhead and Conditional Array Fallbacks
**Learning:** When generating keyed hash maps from Eloquent/Database collections via `pluck()`, using `mapWithKeys()` for simple formatting or redundant type-casting (like casting numeric string keys, which PHP automatically reverts to integers anyway) introduces severe closure invocation overhead. Furthermore, if tests mock the DB to return an array, directly chaining `->toArray()` onto the result will cause a fatal error (`Call to a member function toArray() on array`).
**Action:** Always prefer safely appending `->toArray()` directly to `pluck()` using a conditional check `is_array($result) ? $result : $result->toArray()` instead of iterating over the collection manually. This allows the query builder to construct the associative array natively, avoiding intermediate instantiations, while gracefully handling plain arrays returned in mocked test environments.

## 2024-05-19 - Eloquent get() hydration overhead on mapped loops
**Learning:** Hydrating full Eloquent models via `get()` in query builder chains introduces significant O(N) memory and processing overhead when the code only iterates over specific keys.
**Action:** When mapping database relations internally directly replacing `->get()` with explicit array fetching via `->pluck('value', 'key')` dramatically speeds up batch processing loops. Always prefer safely appending `->toArray()` to pluck using `is_array($result) ? $result : $result->toArray()` instead of iterating over object collections.

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

## 2026-09-29 - Surgical Optimization Edits and No Scratch Script Commits
**Learning:** Running whole-file formatters or regenerating entire components while performing performance optimizations introduces massive whitespace/formatting diffs (1,000+ lines), masking the real optimization, invalidating git blame, and causing painful merge conflicts with concurrent PRs. Additionally, committing scratch benchmark or patch scripts (`patch_*.py`, `test.cjs`) pollutes production repositories and triggers CI guardrail failures.
**Action:** Restrict all algorithmic and performance optimizations to strictly scoped replacement chunks. Diff size must reflect only the functional optimization. Always clean up temporary benchmark or patch scripts with `git rm -f` before committing.
## 2024-10-24 - Delayed toArray() on Eloquent Collections
**Learning:** Calling `toArray()` on a large Eloquent Collection *before* applying filters (e.g., in `listPendingRequests`) forces the framework to fully serialize every single model—evaluating relationships, accessors, mutators, and casting date fields. This introduces massive, unnecessary CPU and memory overhead for models that are subsequently discarded by the array filter.
**Action:** Always filter the Eloquent Collection of models first (using `->filter()`) and call `->toArray()` *only* on the reduced subset before returning the final array. Use `array_values()` if the resulting keys must be sequentially re-indexed.

## Additive Documentation & Scratch Cleanliness Directives
- **Strictly Additive Journal Updates**: When updating `.jules/*.md`, strictly append new dated entries (`## YYYY-MM-DD - Title`). NEVER delete, truncate, or overwrite historical learnings or previous entries.
- **Substantive Code Diff Requirement**: Pull requests must include substantive code changes in `src/`, `app/`, `lib/`, or `tests/`. Never open PRs that modify only `.jules/*.md` journals or root scratch scripts.
- **Zero Scratch File Commits**: Never commit `*.diff`, `*.patch`, `test_*.ts`, `test_*.js`, `test.cjs`, `fix_*.php`, or `patch_*.py` files. Always remove temporary debugging or verification scripts prior to committing.

## 2026-10-01 - Optimize mapWithKeys to keyBy for Eloquent collections
**Learning:** When generating keyed hash maps from Eloquent collections, `mapWithKeys` instantiates a new array for every single item and triggers an inner foreach loop, which is a de-optimization compared to `keyBy`. Using `keyBy(fn($item) => (string)$item->field)` provides the same explicit string casting without the overhead.
**Action:** Use `->keyBy(fn($item) => (string)$item->field)` instead of `mapWithKeys` when strict string casting and high performance are required on Eloquent collections.
