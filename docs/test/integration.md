# Integration Testing Strategy

Integration tests verify the backend working with the real application infrastructure.

For this project, integration testing is primarily a **backend responsibility**.

The required integration boundary is:

```text
HTTP request
→ route
→ middleware
→ validation
→ controller
→ use case / application behavior
→ Eloquent / Query Builder
→ real MySQL test database
```

React does not require a separate integration-test layer by default.

React components and pages are covered by `unit.md`, while complete frontend/backend flows are covered by `e2e.md`.

## Source of truth

This document defines what belongs to the **integration** test level.

The test reviewer must use this document when evaluating integration-test coverage.

## Backend — PHP / Laravel

### Every controller must have integration coverage

Every controller action exposed by the backend must have integration tests.

The test must exercise the real Laravel request flow and the real MySQL test database.

Do not replace controller integration tests with mocks of:

- database;
- Eloquent;
- repository implementation;
- controller dependencies that are part of the behavior being verified.

The purpose is to prove that the application layers are correctly connected.

### Real MySQL database

Integration tests must use the same database engine defined by the project:

**MySQL 8**

Do not silently replace MySQL with SQLite or another engine.

This matters especially for:

- constraints;
- transactions;
- locks;
- concurrent writes;
- SQL semantics;
- relationships;
- indexes;
- database-dependent validation.

The integration database must be isolated from development and production.

Never:

- connect tests to production;
- use production credentials;
- reuse production data;
- depend on shared persistent state.

## Controller input coverage

Every controller action must cover the relevant request contract.

At minimum, when applicable:

- valid request;
- invalid request reaching Laravel validation;
- missing required input;
- invalid route parameter;
- invalid query parameter;
- unauthenticated request;
- unauthorized request;
- referenced resource not found;
- database-dependent validation failure;
- expected success response or redirect;
- expected validation/error response;
- expected persistent effect.

The exhaustive validation matrix belongs to `unit.md`.

Integration tests verify that validation is correctly connected to the real HTTP/controller/database flow.

Do not repeat every unit validation permutation here.

## Persistence behavior

Integration tests are mandatory for behavior involving:

- Eloquent persistence;
- Query Builder;
- relationships;
- foreign keys;
- database constraints;
- transactions;
- rollback;
- database-dependent validation;
- locks;
- concurrency;
- persisted state used by application decisions;
- persistence followed by reading.

Tests must assert observable effects.

Examples:

- row created;
- row not created;
- row updated;
- row deleted;
- relationship persisted;
- invalid operation leaves state unchanged;
- transaction rolled back;
- constraint rejects invalid state;
- concurrent requests preserve the required invariant.

Do not treat `no exception was thrown` as sufficient proof.

## Concurrency

When correctness depends on simultaneous requests or writes, integration tests must exercise the real database strategy.

A sequential test does not prove concurrency safety.

Mocks do not prove:

- MySQL locks;
- transaction isolation;
- real concurrent writes;
- constraint behavior under contention.

Avoid arbitrary timing.

Use deterministic synchronization when technically possible.

## Laravel testing facilities

Use Laravel testing facilities when appropriate, such as:

- factories;
- HTTP request helpers;
- authentication helpers;
- database assertions;
- time control.

Do not fake the infrastructure whose real behavior is being verified.

## React / TypeScript

A separate React integration layer is **not mandatory** for this project.

React behavior should normally be covered by:

- `unit.md` for components, pages, hooks, forms, validation, and UI states;
- `e2e.md` for real React ↔ Laravel ↔ MySQL flows.

Only add a distinct React integration test when there is a concrete frontend boundary that cannot be meaningfully protected as a component/unit test and does not justify a full E2E test.

Do not create a React integration suite merely to fill a testing category.

## PHP ↔ React contract

Backend integration tests verify the real backend side of the contract.

React unit tests verify how the frontend consumes the expected contract in isolation.

The real cross-stack contract is verified by selected E2E tests.

Relevant contract elements include:

- field names;
- validation errors;
- redirects;
- status behavior;
- Inertia props;
- form payload shape.

## Infrastructure failure

If required integration infrastructure is unavailable:

```text
NOT_EXECUTED
```

Never report the integration test as `PASSED`.

A task requiring backend integration is not fully verified until the real integration test runs successfully.

## Existing tests are contracts

Do not:

- delete integration tests to hide regressions;
- replace real integration coverage with mocks because the test became difficult;
- reduce assertions only to make the suite pass;
- change expected behavior without an approved behavior change;
- skip required database tests without justification.

## Final rule

Before creating an integration test, answer:

**Which real backend boundary does this test prove?**

For this project, the expected answer normally includes:

**Laravel controller flow + real MySQL test database.**
