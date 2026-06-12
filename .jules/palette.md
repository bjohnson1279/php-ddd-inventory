## 2024-06-12 - Added Keyboard Support to Notification Items
**Learning:** Found that custom clickable `div` elements used as list items for notifications lacked `tabIndex`, `role="button"`, `aria-label`, and keyboard event handlers. This is a common pattern that makes interfaces inaccessible to keyboard and screen reader users.
**Action:** Always verify that interactive elements that are not native `<button>` or `<a>` tags have the appropriate ARIA roles, `tabIndex`, and keyboard event handlers (`onKeyDown` for Enter/Space).
