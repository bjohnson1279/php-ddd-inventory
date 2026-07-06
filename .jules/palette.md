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
