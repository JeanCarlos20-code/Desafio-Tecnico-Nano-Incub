# Integration Testing Strategy

Integration tests verify the backend working with the real Laravel stack and real project infrastructure.

For this project, integration testing is primarily a **backend responsibility**.

The expected integration flow is:

```text
HTTP request
→ route
→ middleware
→ validation
→ controller
→ application/use case
→ Eloquent / Query Builder
→ real MySQL test database
```

React does not require a separate integration-test layer by default.

React behavior belongs to `unit.md`, while complete React ↔ Laravel ↔ MySQL flows belong to `e2e.md`.

## Source of truth

This document defines the **integration test** level.

The test reviewer must use this document when evaluating integration coverage.

## Backend — PHP / Laravel

### Every controller must have integration coverage

Every controller action exposed by the backend must have integration tests.

The purpose is to prove that the real request flow works correctly with the database and other real application infrastructure involved in that flow.

Integration tests should exercise, as applicable:

```text
route
→ middleware
→ validation
→ controller
→ application/use case
→ persistence
→ response
```

Do not replace controller integration tests with mocks of infrastructure whose real behavior is part of the test.

### Real MySQL database

Integration tests must use the same database engine defined by the project:

**MySQL 8**

Do not silently replace MySQL with SQLite or another database engine.

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

Use the reset/isolation strategy adopted by the project.

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

Integration tests verify that validation is correctly connected to the real request/controller/database flow.

Do not repeat every validation permutation already protected by unit tests.

## Persistence and real infrastructure

Integration tests are mandatory when behavior depends on:

- Eloquent persistence;
- Query Builder;
- relationships;
- foreign keys;
- database constraints;
- transactions;
- rollback;
- locks;
- concurrency;
- database-dependent validation;
- persisted state used by application decisions;
- persistence followed by reading;
- other real infrastructure that is part of the controller flow.

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
- concurrent operations preserve the required invariant.

`No exception was thrown` is not sufficient proof.

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

Use Laravel testing facilities when appropriate, including:

- factories;
- HTTP request helpers;
- authentication helpers;
- database assertions;
- time control.

Do not fake the infrastructure whose real behavior is being verified.

If a queue, event, notification, filesystem, or other dependency is part of the behavior under test, decide explicitly whether it must be real or replaced according to the test objective.

Do not use a fake that prevents the behavior being tested from executing.

## React / TypeScript

A separate React integration suite is **not mandatory** for this project.

React behavior should normally be protected by:

- `unit.md` for components, pages, hooks, forms, validation, states, and interactions;
- `e2e.md` for the real React ↔ Laravel ↔ MySQL flow.

Only introduce a distinct React integration test when a concrete frontend boundary cannot be meaningfully covered as a unit/component test and does not justify full E2E coverage.

Do not create React integration tests merely to fill a testing category.

## PHP ↔ React contract

Backend integration verifies the real backend side of the contract.

React unit tests verify how the frontend consumes the expected contract in isolation.

Selected E2E tests verify the real cross-stack contract.

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

A task requiring integration coverage is not fully verified until the required real infrastructure test executes successfully.

## Coverage

Backend integration tests contribute to PHP coverage.

For this project, the main PHP coverage should be based on:

```text
Unit + Integration
```

Integration coverage is especially useful for controllers and other backend paths intentionally exercised only with the real Laravel/MySQL flow.

Do not require E2E tests to contribute to the main PHP coverage target.

## Existing tests are contracts

Do not:

- delete integration tests to hide regressions;
- replace real integration coverage with mocks because the test became difficult;
- reduce assertions only to make the suite pass;
- change expected behavior without an approved behavior change;
- skip required database tests without justification.

## CI/CD acceptance rules

Integration verification passes only when all required backend integration tests execute successfully against the real project database engine.

The integration gate must satisfy all of the following:

- every affected controller action has the required integration coverage;
- the real Laravel request flow executes successfully;
- the integration environment uses MySQL 8;
- migrations/setup required by the tests complete successfully;
- relevant authentication and authorization scenarios pass;
- relevant validation reaches the real request/controller flow;
- expected database writes, reads, rollbacks, constraints, and relationships are verified;
- concurrency tests pass when the changed behavior depends on concurrency;
- no required integration test is skipped without an approved reason;
- required backend lint, build, and unit gates also pass.

### Database requirement

The integration suite must use an isolated MySQL test database.

It must not:

- use production credentials;
- connect to production;
- reuse production data;
- silently replace MySQL with SQLite or another engine;
- bypass the real persistence layer with mocks when persistence is part of the behavior being verified.

### Coverage

Integration tests may contribute to PHP coverage when the pipeline is configured to collect it.

However, integration correctness is the primary purpose of this gate.

Do not claim combined Unit + Integration coverage unless the CI/CD pipeline explicitly collects and combines both results.

### Incomplete verification

Integration verification is incomplete when:

- MySQL cannot start;
- database connection fails;
- migrations/setup fail;
- required integration tests do not execute;
- real persistence is replaced by a mock for a behavior that requires the database;
- a required integration scenario is skipped.

Do not report incomplete integration verification as passed.

## Final rule

Before creating an integration test, answer:

**Which real backend flow or infrastructure boundary does this test prove?**

For this project, the expected answer normally includes:

**Laravel controller flow + real MySQL test database + any other real dependency required by that controller flow.**
