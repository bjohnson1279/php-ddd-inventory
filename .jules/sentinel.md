## 2024-05-18 - Fix Authorization Bypass in Setup Endpoint
**Vulnerability:** The `/api/setup` endpoint allowed anyone to create an `admin` account in an *existing* tenant's organization because it relied on `insertOrIgnore` for the tenant record but continued executing user creation logic regardless.
**Learning:** `insertOrIgnore` swallows unique constraint violations silently. If subsequent logic depends on the creation of that specific record to establish boundaries (like creating an initial admin user for a new tenant), using it allows attackers to piggyback onto existing records to escalate privileges or bypass authorization.
**Prevention:** Always explicitly check for the existence of boundary/container records (like tenants or organizations) before creating initial administrative users. Do not rely on ignored constraint errors to handle existence checks if subsequent logic must only run for newly created boundaries.

## 2026-06-22 - Prevent IDOR in AssignRoleToUser
**Vulnerability:** IDOR (Insecure Direct Object Reference) in `AssignRoleToUser` where a malicious admin from one tenant could assign roles to a user in another tenant because there was no cross-tenant isolation check between the acting user and target user.
**Learning:** Even if the actor's permissions are verified (`canDo('users:manage')`), we must ensure the actor's tenant ID matches the target entity's tenant ID to enforce true multi-tenancy boundaries.
**Prevention:** Always verify that `$actor->getTenantId()->getValue() === $target->getTenantId()->getValue()` before allowing cross-user modifications.
