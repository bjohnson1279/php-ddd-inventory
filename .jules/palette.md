## 2025-02-28 - Missing label-to-input association in React app
**Learning:** Multiple forms across this React app wrap text in `<label>` tags but fail to explicitly link them to inputs using `htmlFor` and `id` attributes. This breaks screen reader accessibility and reduces click target sizes, making the UI harder to use for users with impaired motor skills.
**Action:** When creating or auditing new forms in this app, ensure every `<label>` has an `htmlFor` attribute that exactly matches the `id` of its corresponding `<input>` or `<select>`.
