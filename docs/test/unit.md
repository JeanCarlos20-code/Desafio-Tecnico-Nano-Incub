# Unit Testing Strategy

Unit tests protect isolated business behavior, input validation, and frontend behavior that can be verified without the real backend or database.

They must be fast, deterministic, and independent from real infrastructure.

## Source of truth

This document defines what belongs to the **unit** test level.

The test reviewer must use this document when evaluating unit-test coverage.

## Backend — PHP / Laravel

Backend unit tests are mandatory for:

1. **Use cases / business rules**
2. **Input validation**

### Use cases and business rules

Every use case or application operation that contains business decisions must have unit tests for its observable behavior.

Cover, when applicable:

- valid behavior;
- business-rule rejection;
- boundary values;
- calculations;
- state transitions;
- isolated authorization decisions;
- relevant error paths.

Use cases must not depend on the real database in unit tests.

Mock or fake only real boundaries required by the use case, such as:

- repository;
- clock;
- external service;
- storage;
- notification gateway.

Do not mock the business rule being tested.

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
- nullable / optional values;
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
- minimum and maximum boundaries;
- invalid allowed values;
- missing values;
- invalid nullability;
- invalid nested structures;
- invalid date/time formats.

For Laravel, validation may live in:

- `FormRequest`;
- custom validation rules;
- dedicated input objects;
- application-level validators.

Do not test Laravel's validation engine itself.

Test the validation contract defined by the project.

### Database-dependent validation

Validation that requires persisted state is not purely unit-level.

Examples:

- `unique`;
- `exists`;
- ownership based on persisted data;
- validation depending on current database state.

Pure validation behavior should remain covered by unit tests.

Database-dependent behavior must also be covered by `integration.md`.

## Frontend — React / TypeScript

React is also covered by unit tests.

Unit tests should protect frontend behavior that can be verified without the real Laravel backend or a real browser workflow.

This includes:

- components;
- pages in isolation;
- custom hooks;
- forms;
- client-side validation;
- conditional rendering;
- loading states;
- error states;
- empty states;
- success states;
- user interaction;
- reducers;
- pure functions;
- formatters;
- parsers;
- state transitions;
- frontend-owned validation.

A React unit test may render a component tree and interact with it.

The important boundary is that it does **not** require the real backend or real MySQL database.

### Component tests

For meaningful React components, test behavior from the user's perspective.

Prefer assertions such as:

- element is visible;
- button can be clicked;
- form accepts input;
- validation message appears;
- loading indicator appears;
- error state is rendered;
- successful state is rendered;
- conditional content appears or disappears;
- callback or navigation boundary is invoked correctly.

Avoid testing:

- internal component state directly;
- private implementation details;
- CSS classes unless styling itself is the behavior;
- framework internals.

### React Testing Library

If React Testing Library is configured, prefer semantic queries such as:

- role;
- label;
- text.

Avoid relying unnecessarily on:

- internal DOM structure;
- CSS selectors;
- `data-testid` everywhere.

`data-testid` is acceptable when no suitable semantic selector exists.

### Frontend boundaries

Mock only external boundaries needed by the test, such as:

- HTTP/backend client;
- Inertia/router boundary;
- browser API;
- clock;
- storage;
- external library integration.

Do not mock every child component.

The component tree under test should remain meaningful.

### Frontend validation

If React performs validation for user experience, test it at unit level.

Frontend validation never replaces backend validation.

The backend remains authoritative for data entering the system.

## What unit tests must not prove

Do not use unit tests to prove:

- real controller wiring;
- real Laravel middleware;
- real Eloquent behavior;
- migrations;
- MySQL constraints;
- real transactions;
- locks;
- database concurrency;
- complete PHP ↔ React integration;
- real browser workflows.

Those belong to integration or E2E tests.

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
- mocking validation logic to make a test pass;
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

## Existing tests are contracts

Do not weaken a valid existing test only because production code changed.

Do not:

- remove scenarios just to make the suite green;
- reduce assertions without a behavioral reason;
- change expected results to match incorrect code;
- add mocks only to hide a regression;
- skip failing tests without justification.

## Final rule

Before creating a unit test, answer:

**Which isolated backend rule, input contract, React behavior, or frontend state transition does this test protect?**

If there is no clear answer, the test probably does not belong at the unit level.
