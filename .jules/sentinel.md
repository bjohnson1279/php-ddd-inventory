## 2026-06-22 - Prevent IDOR in AssignRoleToUser
**Vulnerability:** IDOR (Insecure Direct Object Reference) in `AssignRoleToUser` where a malicious admin from one tenant could assign roles to a user in another tenant because there was no cross-tenant isolation check between the acting user and target user.
**Learning:** Even if the actor's permissions are verified (`canDo('users:manage')`), we must ensure the actor's tenant ID matches the target entity's tenant ID to enforce true multi-tenancy boundaries.
**Prevention:** Always verify that `$actor->getTenantId()->getValue() === $target->getTenantId()->getValue()` before allowing cross-user modifications.
