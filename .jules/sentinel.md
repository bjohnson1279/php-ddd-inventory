## 2024-06-18 - Fix cross-tenant authorization bypass in AssignRoleToUser
**Vulnerability:** The AssignRoleToUser use-case checked for `users:manage` permissions but neglected to verify that the actor and the target user belonged to the same tenant.
**Learning:** Permission checks are necessary but not sufficient for multi-tenant applications; boundary checks (Tenant ID matching) are critical to prevent horizontal privilege escalation and IDOR.
**Prevention:** Always verify tenant boundaries when an actor requests modifications to a specific target domain entity.
