## 2024-06-17 - ARIA Live Regions for Inline Validation
**Learning:** Simple React state-driven feedback messages (like conditionally rendered `<p>` tags for success/error text) are entirely missed by screen readers unless they are wrapped in semantic ARIA live regions or status roles.
**Action:** When adding or maintaining dynamic inline validation or success/error messages, always ensure the container uses `role="status"` or `role="alert"` alongside the appropriate `aria-live` attribute (`polite` vs `assertive`) to guarantee the feedback is perceivable to assistive technologies.

## 2025-02-28 - Missing label-to-input association in React app
**Learning:** Multiple forms across this React app wrap text in `<label>` tags but fail to explicitly link them to inputs using `htmlFor` and `id` attributes. This breaks screen reader accessibility and reduces click target sizes, making the UI harder to use for users with impaired motor skills.
**Action:** When creating or auditing new forms in this app, ensure every `<label>` has an `htmlFor` attribute that exactly matches the `id` of its corresponding `<input>` or `<select>`.
## 2024-06-24 - Focus States and Button Accessibility
**Learning:** The application lacked clear `focus-visible` outlines for interactive elements, which is a major accessibility issue for keyboard users.
**Action:** Added `focus-visible` styles to `button` elements to ensure clear keyboard focus indicators. Also ensured buttons with `disabled` state communicate this visually by reducing opacity and changing the cursor to `not-allowed`.

## 2024-06-24 - Cursor Style Cleanup
**Learning:** Inline styles with conditional `cursor: not-allowed` were being used extensively when disabled styles were better handled centrally in CSS for consistency.
**Action:** Centralized disabled button styles in `styles.css` using `button:disabled` to improve maintainability and ensure consistent UX across all buttons.

## 2026-06-25 - Handling Time-Series Data in UI Components
**Learning:** Displaying time-series historical data (like ledger entries or stock transactions) requires clean sorting and efficient pagination to prevent DOM bloat and layout shift when huge lists are loaded.
**Action:** Always implement server-side pagination, sorting by timestamp, and clear date/time formatters in UI displays of ledger, transaction, or dispatch lists. Ensure that dynamic alert messages or state loading components (like fetching older history chunks) use appropriate ARIA live regions to notify the user of background updates.
## 2025-02-28 - Immediate Visual Feedback for Async Operations
**Learning:** During form submission or async actions, relying solely on text changes (e.g., "Processing...") can lack visual prominence, making users unsure if an action was registered. Adding an animated spinner alongside the text creates an immediate, noticeable visual cue that prevents double-submissions.
**Action:** Always include a visual loading indicator (like an animated SVG spinner) within primary action buttons when the application enters a loading state. Ensure the button utilizes flexbox for proper alignment between the spinner and text.

## 2026-06-27 - Dynamic Form Feedback
**Learning:** Dynamically rendered form status messages require role="alert" to notify screen readers.
**Action:** Always add role="alert" to dynamic success/error message containers.

## 2025-02-28 - Standardizing Loading States and ARIA attributes
**Learning:** The application contained hardcoded emojis (like `⏳`) used as loading indicators, which can be misread by screen readers and visually inconsistent. Furthermore, manually setting `disabled` utility classes instead of using the native HTML `disabled` attribute reduces accessibility and native keyboard protections.
**Action:** Always replace informal loading emojis with the standardized `<Spinner />` component. Ensure buttons use the native `disabled={isLoading}` attribute to prevent duplicate actions and apply `aria-busy={isLoading}` to accurately communicate the asynchronous loading state to screen readers.

## 2024-07-03 - CSS :has() for Required Fields
**Learning:** Using the `:has(:required)` CSS pseudo-class to automatically style labels for required form fields is a highly scalable and robust way to improve form accessibility and usability across an entire application, avoiding the fragility of manually updating individual components.
**Action:** Always prefer CSS-level styling for global UX patterns like required indicators when possible to ensure consistency and maintainability.

## 2024-05-24 - Async Loading States for Multi-Trigger Operations
**Learning:** When adding loading states to API functions that can be triggered manually (e.g., a "Search" button) or automatically in the background (e.g., refreshing data after an update), applying the loading state at the button handler level misses the background operations, leaving users with a disappearing UI and no feedback.
**Action:** Always wrap the core data-fetching function (e.g., `fetchConfig`) with the loading state, rather than just the specific button click handlers, to ensure consistent loading feedback across all interaction paths.

## 2026-05-30 - Standardizing Loading Spinners
**Learning:** Using inline text emojis (like 🔄) and attaching a spin CSS class to indicate a loading state is inaccessible for screen readers. Using the standard `<Spinner />` component with `aria-busy` and visually hiding purely decorative elements provides a much more accessible and consistent user experience.
**Action:** Replace ad-hoc emoji spinners with the standard `<Spinner />` component across the application and ensure appropriate `aria-busy` attributes are set on parent interactive elements.

## 2024-07-07 - Dynamic form feedback using role=alert
**Learning:** Dynamic form feedback requires role="alert" attribute on its container element to notify screen readers of any changes.
**Action:** Add role="alert" attribute on field error element containers and toast error notification element.

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
- **No Journal-Only PRs**: Never open a pull request that only contains updates to .jules/*.md files without corresponding functional code changes and tests.
- **Stale Suggestion Guard**: Always verify the current code on `main`/`master` before planning changes. If no actionable diff is required, cancel task execution immediately.

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

## 2026-09-29 - Scope Verification for Async Loading Attributes
**Learning:** Blindly injecting `disabled={loading}` or `aria-busy={loading}` into JSX/TSX buttons causes fatal TypeScript compilation errors (`TS2304: Cannot find name 'loading'`) when `loading` is not declared in component props, state hooks (`useState`), or mutation results. Furthermore, using temporary patch scripts (`fix_*.cjs`) to manipulate source code pollutes the git index.
**Action:** Before referencing any state identifier (such as `loading`, `isSubmitting`, `isPending`) in `disabled` or `aria-busy`, inspect the component scope. If no loading state is tracked, define it using `useState(false)` or check existing query/mutation hooks. Never bind undeclared variables. Always run `tsc --noEmit` locally and never commit temporary fix scripts.

## Additive Documentation & Scratch Cleanliness Directives
- **Strictly Additive Journal Updates**: When updating `.jules/*.md`, strictly append new dated entries (`## YYYY-MM-DD - Title`). NEVER delete, truncate, or overwrite historical learnings or previous entries.
- **Substantive Code Diff Requirement**: Pull requests must include substantive code changes in `src/`, `app/`, `lib/`, or `tests/`. Never open PRs that modify only `.jules/*.md` journals or root scratch scripts.
- **Zero Scratch File Commits**: Never commit `*.diff`, `*.patch`, `test_*.ts`, `test_*.js`, `test.cjs`, `fix_*.php`, or `patch_*.py` files. Always remove temporary debugging or verification scripts prior to committing.

## Scope Quarantine, Journaling & Security Test Invariants
- **Strictly Append-Only Journaling**: When adding learnings to `.jules/*.md`, append strictly at the end of the file. Do not rewrite, deduplicate, or remove lines beginning with `## YYYY-MM-DD`.
- **Surgical Scope Quarantine**: Modify only the files directly involved in the issue and their corresponding test fixtures. Do not delete, rename, or perform drive-by cleanups of unrelated root-level scripts or legacy files.
- **Coupled Test Fixture Awareness for Security Invariants**: When changing fail-open fallback behavior (such as hardening decryption to fail closed), always update upstream test mocks that rely on plaintext credentials or mock values.

## 2026-10-07 - Process Streamlining, Sibling Coalescence & Autoloading Invariants
**Learning:**
1. Fragmenting stub methods across multiple micro-PRs on the same class causes unavoidable sibling merge collisions and wasted CI cycles.
2. Placing multiple domain services into a single file breaks Composer PSR-4 autoloader discovery in PHP, triggering fatal `Class not found` errors.
3. Writing service calls against unverified entity methods causes fatal runtime errors.
4. String-escaping markdown journal updates corrupts rendered formatting.

**Action:**
- **Coalesce Micro-PRs**: When implementing or scaffolding related controller endpoints, stub methods, or repository queries on a single class, consolidate all changes into a single coherent pull request. Never create separate fragmented PRs for each individual method of the same class.
- **Strict PSR-4 Isolation in PHP**: In PHP codebases, place every class, interface, and enum in its own file named `<ClassName>.php` matching its namespace path. Never combine multiple domain classes into a single file.
- **Domain Contract Verification**: Always inspect entity and aggregate root definitions to verify exact method and property names before writing service logic or test fixtures.
- **Clean Markdown Formatting**: Always append journal entries using actual newline characters, never literal string escape sequences.
