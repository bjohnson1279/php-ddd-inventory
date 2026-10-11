# Hexagonal Architecture & Dependency Isolation Guidelines

## Layer Boundaries
1. **Domain Layer (`src/Domain/`)**: Core domain entities, value objects, and domain repository interfaces. Independent of Laravel framework classes or database ORMs.
2. **Application Layer (`src/Application/`)**: Command handlers and use cases. Interacts with Domain Repositories via interfaces.
3. **Infrastructure Layer (`src/Infrastructure/`)**: Eloquent ORM repository implementations, ServiceContainer bindings, and external integrations.
4. **Presentation Layer (`src/Presentation/`, Controllers)**: HTTP REST controllers, middleware, and request/response serialization.

## Automated Refactoring Rules
- **Fail-Closed Security**: Missing security configuration variables must throw `\RuntimeException` in non-testing environments (`APP_ENV !== 'testing'`).
- **Safe Serialization**: `unserialize()` calls MUST pass an explicit `allowed_classes` whitelist (e.g. `AllowedClasses::get()`).
- **Test-Pairing Mandatory Directive**: Every refactor or bug fix MUST include corresponding PHPUnit test coverage under `tests/`.

- **Centralize Shared Test Doubles for Abstract Domain Repositories**: When mocking domain repositories across multiple test suites, define a single shared test double rather than duplicating inline mocks to prevent cross-suite synchronization bugs.

- **Surgical Edits Only (No Whole-File Formatting)**: Never run whole-file code formatters (Prettier, Black, Pint, rustfmt) across unmodified lines. Changes must be strictly range-scoped and limited to the minimal AST block needed. Avoid noisy quote/whitespace churn that masks real logic changes and causes merge conflicts. Verify with `git diff -w` that non-functional churn is zero.
