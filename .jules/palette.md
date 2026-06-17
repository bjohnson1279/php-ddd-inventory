## 2024-06-17 - ARIA Live Regions for Inline Validation
**Learning:** Simple React state-driven feedback messages (like conditionally rendered `<p>` tags for success/error text) are entirely missed by screen readers unless they are wrapped in semantic ARIA live regions or status roles.
**Action:** When adding or maintaining dynamic inline validation or success/error messages, always ensure the container uses `role="status"` or `role="alert"` alongside the appropriate `aria-live` attribute (`polite` vs `assertive`) to guarantee the feedback is perceivable to assistive technologies.
