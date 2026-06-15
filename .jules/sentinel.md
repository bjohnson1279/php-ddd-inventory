## 2024-05-24 - Missing Authorization Check on User Endpoints
**Vulnerability:** The `POST /api/users` and `GET /api/users` endpoints lacked explicit authorization checks for the `users:manage` permission, allowing any authenticated user to create or list users.
**Learning:** Checking `requireAuth()` only validates that the user holds a valid token, but it does not check their roles or permissions. Privileged operations must explicitly check `$actor->canDo('users:manage')`.
**Prevention:** Always verify both authentication and authorization. Ensure that endpoints performing administrative tasks explicitly fetch the acting user and validate their permissions against the required action.
