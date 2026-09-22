1. Use `replace_with_git_merge_diff` to add `requireAuth();` and replace hardcoded `$tenantId = 'tenant-1';` with `$tenantId = tenantId();` for the `/api/lots/quarantine` endpoint, preserving the `json_decode(file_get_contents('php://input'))` line.
2. Verify the changes using `sed -n '2660,2685p' public/index.php`.
3. Use `replace_with_git_merge_diff` to add `requireAuth();` and replace hardcoded `$tenantId = 'tenant-1';` with `$tenantId = tenantId();` for the `/api/lots/recall` endpoint, preserving the `json_decode` logic.
4. Verify the changes using `sed -n '2685,2710p' public/index.php`.
5. Use `replace_with_git_merge_diff` to add `requireAuth();` and replace hardcoded `'tenantId' => 'tenant-1',` with `'tenantId' => tenantId(),` for the `/api/lots/release` endpoint.
6. Verify the changes using `sed -n '2710,2730p' public/index.php`.
7. Use `replace_with_git_merge_diff` to add `requireAuth();` and replace hardcoded `tenantId: 'tenant-1',` with `tenantId: tenantId(),` for the `/api/lots/*/traceability` endpoint.
8. Verify the changes using `sed -n '2730,2750p' public/index.php`.
9. Use `replace_with_git_merge_diff` to add `requireAuth();` to the `/api/cross-dock/evaluate` endpoint.
10. Verify the changes using `sed -n '2745,2760p' public/index.php`.
11. Use `replace_with_git_merge_diff` to add `requireAuth();` to the `/api/fulfillment/drop-ship` endpoint.
12. Verify the changes using `sed -n '2760,2775p' public/index.php`.
13. Use `run_in_bash_session` to run `composer install` followed by `vendor/bin/phpunit` to ensure functionality is not broken and that the authorization works.
14. Complete pre-commit steps to ensure proper testing, verification, review, and reflection are done.
15. Use `submit` to create PR with the following title and description:
    - Title: "🛡️ Sentinel: [CRITICAL] Fix authorization bypass in API"
    - Description:
        * 🚨 Severity: CRITICAL
        * 💡 Vulnerability: Missing `requireAuth()` and hardcoded `tenantId = 'tenant-1'` in `/api/lots/*`, `/api/cross-dock/evaluate`, and `/api/fulfillment/drop-ship` endpoints.
        * 🎯 Impact: Allows unauthenticated malicious actors to access sensitive lot management and fulfillment API endpoints and potentially access or modify data for another tenant (`tenant-1`), leading to IDOR and authorization bypass.
        * 🔧 Fix: Added `requireAuth();` to the beginning of the affected routes and dynamically resolved the `$tenantId` via the `tenantId()` helper instead of using a hardcoded value.
        * ✅ Verification: Ran `vendor/bin/phpunit` to ensure the test suite continues to pass.
