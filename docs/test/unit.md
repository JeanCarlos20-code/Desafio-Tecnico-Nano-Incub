# Unit Testing Strategy

Unit tests protect isolated backend business behavior, input validation, and isolated frontend behavior.

They must be fast, deterministic, and independent from real infrastructure.

## Source of truth

This document defines the **unit test** level for both backend and frontend.

The test reviewer must use this document when evaluating unit-test coverage.

## Backend — PHP / Laravel

Backend unit tests are required for:

1. application/use cases;
2. business rules;
3. validation for every type of data entering the system.

### Application and use cases

Every use case or application operation containing business decisions must have unit tests for its observable behavior.

Cover, when applicable:

- valid behavior;
- business-rule rejection;
- calculations;
- state transitions;
- relevant boundary values;
- isolated authorization decisions;
- relevant error paths.

Use cases must not use the real database in unit tests.

Mock or fake only real boundaries needed by the use case, such as:

- repository;
- clock;
- external service;
- storage;
- notification gateway.

Do not mock the business rule being tested.

Prefer assertions about observable results rather than internal call order.

### Input validation

Every type of data entering the backend must have validation coverage.

This includes, when applicable:

- request body;
- query parameters;
- route/path parameters;
- form fields;
- IDs;
- strings;
- integers;
- booleans;
- dates and times;
- enums or allowed values;
- nullable and optional values;
- arrays and nested objects;
- files or file metadata;
- filters;
- pagination;
- sorting inputs.

Cover at least:

- required values;
- valid values;
- wrong types;
- malformed values;
- missing values;
- invalid nullability;
- minimum and maximum boundaries;
- invalid allowed values;
- invalid nested structures;
- invalid date/time formats.

Validation may be implemented with:

- `FormRequest`;
- custom validation rules;
- dedicated input objects;
- application-level validators.

Do not test Laravel's validation engine itself.

Test the validation contract defined by the project.

### Database-dependent validation

Validation depending on persisted state is not purely unit-level.

Examples:

- `unique`;
- `exists`;
- ownership based on database state;
- validation depending on currently persisted records.

The pure input contract remains covered by unit tests.

Database-dependent behavior must also be covered by `integration.md`.

### What backend unit tests must not prove

Do not use unit tests to prove:

- controller wiring;
- routing;
- middleware execution;
- real Eloquent behavior;
- migrations;
- MySQL constraints;
- transactions;
- locks;
- database concurrency;
- complete HTTP behavior.

Those belong to `integration.md` or `e2e.md`.

Do not create artificial unit tests for:

- passive Eloquent Models;
- routes that only register endpoints;
- migrations without isolated logic;
- simple seeders;
- configuration;
- constants;
- getters/setters;
- framework internals.

## Frontend — React / TypeScript

React is also covered by unit tests.

For this project, frontend unit tests are the default level for React behavior that can be tested without the real Laravel backend or real MySQL database.

This includes:

- components;
- pages in isolation;
- custom hooks;
- forms;
- frontend validation;
- conditional rendering;
- loading states;
- error states;
- empty states;
- success states;
- user interactions;
- reducers;
- pure functions;
- formatters;
- parsers;
- state transitions.

A React unit test may render a meaningful component tree and interact with it.

The important boundary is:

```text
React behavior
→ real component/hook code
→ backend/network boundary mocked or controlled
```

### Component behavior

Test components from the user's perspective whenever possible.

Prefer assertions such as:

- element is visible;
- button can be clicked;
- form accepts input;
- validation message appears;
- loading state appears;
- error state is rendered;
- success state is rendered;
- conditional content appears or disappears;
- expected navigation/submission boundary is invoked.

Avoid testing:

- internal state directly;
- private implementation details;
- framework internals;
- CSS classes unless visual styling itself is the behavior.

### React Testing Library

If React Testing Library is configured, prefer semantic queries:

- role;
- label;
- text.

Avoid unnecessary dependence on:

- DOM implementation details;
- CSS selectors;
- `data-testid` everywhere.

`data-testid` is acceptable when no suitable semantic selector exists.

### Frontend boundaries

Mock only external boundaries needed by the test, such as:

- backend/HTTP client;
- Inertia/router boundary;
- browser API;
- clock;
- storage;
- external library integration.

Do not mock every child component.

The rendered tree should remain meaningful.

### Frontend validation

If React performs validation for user experience, test that validation at unit level.

Frontend validation never replaces backend validation.

The backend remains authoritative for data entering the system.

## Mocking rules

Mocks are allowed only at real boundaries.

Good examples:

- repository;
- backend client;
- external API;
- storage;
- clock;
- browser API.

Avoid:

- mocking the use case being tested;
- mocking validation logic only to make the test pass;
- mocking every React child component;
- asserting only internal call order.

## Quality requirements

Every unit test must be:

- deterministic;
- focused;
- readable;
- independent;
- fast;
- resilient to internal refactoring.

Avoid:

- `sleep()`;
- real network access;
- real database access;
- production services;
- shared mutable state;
- test-order dependence;
- uncontrolled randomness.

## Coverage

Unit tests contribute to coverage.

For PHP, the main backend coverage is produced by **Unit + Integration** tests.

For React, unit/component tests are the primary source of frontend coverage.

Do not create trivial tests only to increase coverage.

Coverage does not replace meaningful behavior protection.

## Existing tests are contracts

Do not weaken a valid existing test only because production code changed.

Do not:

- remove scenarios just to make the suite green;
- reduce assertions without a behavioral reason;
- change expected results to match incorrect code;
- add mocks only to hide a regression;
- skip failing tests without justification.

When behavior intentionally changes, update tests only after that change is approved.

## CI/CD acceptance rules

Unit verification passes only when all required backend and frontend unit tests succeed.

### Backend

The backend unit gate must satisfy all of the following:

- all required application/use-case tests pass;
- all required backend validation tests pass;
- no required unit test is skipped without an approved reason;
- the configured PHP coverage threshold is met;
- backend code affected by the task remains within the project coverage policy;
- failures in required PHP lint or build gates invalidate the verification.

The current project target for backend application coverage is:

```text
>= 80%
```

Coverage must be enforced by the configured test/coverage tooling.

A report that only displays coverage without failing below the threshold does not satisfy the CI/CD rule.

### Frontend

The frontend unit gate must satisfy all of the following:

- all required React/TypeScript unit and component tests pass;
- all required frontend validation and interaction tests pass;
- no required frontend unit test is skipped without an approved reason;
- the configured frontend coverage threshold is met;
- failures in required frontend lint or build gates invalidate the verification.

The current project target for React unit/component coverage is:

```text
>= 80%
```

### Incomplete verification

Unit verification is incomplete when:

- required tests did not execute;
- coverage could not be collected when it is required;
- the configured threshold was not actually enforced;
- a required lint/build gate failed;
- a required dependency for the test environment was unavailable.

Do not report incomplete verification as passed.

## Final rule

Before creating a unit test, answer:

**Which isolated backend rule, validation contract, React behavior, or frontend state transition does this test protect?**

If there is no clear answer, the test probably does not belong at unit level.
