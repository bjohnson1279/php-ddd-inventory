## 2024-07-30 - Fallback Secret Exposure
**Vulnerability:** The ComplianceLedgerService hardcoded a fallback private key if the `APP_ENV` environment variable was not set, allowing production environments to default to a known weak key if misconfigured.
**Learning:** Security configurations must fail closed. When handling environment-specific security settings like private keys, the application should throw an exception if the required variables are missing, rather than silently falling back to a hardcoded testing key unless explicitly running in a verified 'testing' environment.
**Prevention:** Always use strict environment checks (e.g., `getenv('APP_ENV') === 'testing'`) before allowing fallback logic. Default to throwing `\RuntimeException` for missing security configurations in all other cases.

## Prevention Directives for Automated Refactoring
- **Never Overwrite Complete Files**: Always use range-scoped replacement chunks (`StartLine`/`EndLine`) for edits to `schema.prisma`, `index.ts`, `public/index.php`, or DDL SQL scripts.
- **Do Not Remove Core Declarations**: Do not delete existing route registrations or database DDL tables.
- **Environment Isolation Compatibility**: When replacing fallback secrets, preserve test environment execution via `!getenv('APP_ENV')` or `getenv('APP_ENV') === 'testing'`.
- **No Scratch Files**: Never stage or commit `test_*.ts`, `test_*.js`, or `test.js` files to git.


## 2024-07-31 - Insecure Deserialization in Queue Worker
**Vulnerability:** The queue worker deserialized raw database event payloads using `unserialize()` without restricting the allowed classes. This allowed an attacker with database access to inject malicious serialized objects and achieve Remote Code Execution (RCE).
**Learning:** Even when reading from an internal database, serialized data must be treated as untrusted input. When mitigating insecure deserialization with `unserialize()` by utilizing the `allowed_classes` option, avoid injecting massive, hardcoded class arrays directly inline. Instead, extract the whitelist to a central configuration/helper class (e.g., `AllowedClasses::get()`) to maintain readability and reduce brittleness.
**Prevention:** Always pass an explicit array of `allowed_classes` to `unserialize()`, or better yet, migrate to a safer serialization format like JSON for queue payloads.
## 2024-05-20 - Database Password Fallback Exposure
**Vulnerability:** A hardcoded fallback password ('secret') was used for database connections if the `DB_PASSWORD` environment variable was not set, risking exposure in unconfigured production environments.
**Learning:** Security configurations must fail closed. When handling environment-specific security settings like database passwords, the application should throw an exception if the required variables are missing.
**Prevention:** Remove hardcoded fallbacks and use a strict `throw new \RuntimeException` when critical security environment variables are missing. Ensure to clean up any `.orig` or `.rej` files generated during patching to avoid exposing source code.
## 2026-08-12 - Insecure ComplianceLedgerService Fallback Key
**Vulnerability:** The ComplianceLedgerService hardcoded a fallback private key if the `APP_ENV` environment variable was not set, allowing production environments to default to a known weak key if misconfigured.
**Learning:** Incomplete validation of the environment (`!$env`) allowed insecure defaults to activate in production scenarios without a defined environment.
**Prevention:** Always strictly enforce secure fallback logic only in testing (`getenv('APP_ENV\) === 'testing'`). Default to throwing exceptions for missing security credentials across all other environments.
## 2024-08-01 - DB_PASSWORD Fallback Exposure
**Vulnerability:** A hardcoded fallback password ('postgres') was used for database connections across multiple persistence configuration files if the `DB_PASSWORD` environment variable was not set, risking exposure in unconfigured production environments.
**Learning:** Security configurations must fail closed. When handling environment-specific security settings like database passwords, the application should throw an exception if the required variables are missing, unless explicitly running in a verified 'testing' environment.
**Prevention:** Always use strict environment checks (e.g., `getenv('APP_ENV') === 'testing'`) before allowing fallback logic. Default to throwing `\RuntimeException` for missing security configurations in all other cases. Ensure duplicate configuration keys are cleaned up.
