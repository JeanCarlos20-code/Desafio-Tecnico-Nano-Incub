# End-to-End Testing Strategy

End-to-end tests verify the application as a complete system from the user's perspective.

For this project, E2E tests exercise the **real application route and complete user flow** through the frontend and backend.

The full path is:

```text
browser
→ React / Inertia
→ Laravel route
→ middleware
→ validation
→ controller
→ application/use case
→ MySQL test database
→ response
→ rendered React UI
```

E2E proves that the complete application works together.

It does not replace unit or integration testing.

## Source of truth

This document defines the **E2E test** level.

The test reviewer must use this document when evaluating E2E coverage.

## Scope

E2E tests start from the application's real external entry point.

For this project, that normally means a real browser interacting with a real route and real UI.

E2E should prove selected complete workflows such as:

- opening a real application route;
- authentication through the UI;
- accessing protected routes;
- submitting a real form;
- creating data through React and Laravel;
- editing data through the real UI;
- deleting or cancelling through the real UI;
- receiving backend validation in React;
- redirects and navigation;
- persisted state visible after reload/navigation;
- filters/searches crossing frontend and backend.

The focus is the complete user-visible flow, not isolated implementation details.

## Real stack

E2E should use, as applicable:

- real browser automation;
- real React application;
- real Inertia integration;
- real Laravel application;
- real application routes;
- real middleware;
- real validation;
- real controllers;
- real application/use case code;
- real MySQL test database.

Do not mock:

- Laravel backend;
- MySQL;
- React pages involved in the workflow.

Approved external third-party boundaries may use test doubles or sandbox environments when explicitly defined by the project.

## Route testing

E2E tests must enter through the real application route.

Examples:

```text
GET /rooms
POST /rooms
PUT /rooms/{room}
DELETE /rooms/{room}
```

The exact interaction may happen through React/Inertia rather than manually issuing the HTTP request.

The important point is that the E2E test proves the route as part of the real application flow.

A Laravel HTTP test that reaches a route/controller/MySQL without a real browser is considered **Integration** in this project, not E2E.

## Which flows need E2E

E2E coverage should be selective.

Create E2E tests for:

- critical administrator workflows;
- important create/edit/delete flows;
- authentication/session flows;
- important routes that cross React, Laravel, and MySQL;
- cross-stack regressions not adequately protected at a lower level;
- important Laravel ↔ React contracts.

Do not create E2E tests for every validation rule.

Do not repeat every controller integration scenario through the browser.

Do not repeat every React unit/component test through E2E.

## Validation

E2E should verify representative validation behavior only.

Example:

1. open the real form;
2. submit invalid data;
3. Laravel rejects the request;
4. React displays the returned validation error;
5. invalid data is not persisted.

The exhaustive validation matrix belongs to `unit.md`.

Controller wiring and database-dependent validation belong to `integration.md`.

## Authentication and authorization

When authentication or protected access is part of a critical workflow, E2E should exercise the real behavior.

Examples:

- unauthenticated user cannot access a protected route;
- administrator can log in through the real UI;
- authenticated session persists as expected;
- protected page becomes accessible;
- logout removes access.

Frontend visibility alone is never proof of backend authorization.

## React behavior in E2E

Use E2E when React behavior only becomes meaningful with the real backend.

Examples:

- Laravel validation errors appear in the real form;
- backend redirect reaches the expected React page;
- persisted data appears after navigation;
- filtering works through UI and backend;
- update/delete changes both database state and visible UI;
- authentication state changes the rendered application.

Component-only behavior belongs to `unit.md`.

## Database isolation

Never use production data or credentials.

Use an isolated E2E database.

Each scenario must start from a known state.

Tests must not depend on execution order.

Use the reset/seed strategy defined by the project.

## Assertions

Assert user-visible outcomes and important persistent effects.

Good assertions include:

- expected page or element is visible;
- validation message appears;
- navigation reaches the expected route/page;
- success feedback appears;
- persisted data remains after reload;
- removed/cancelled data is no longer available as expected;
- unauthorized user cannot complete the operation.

Avoid assertions about:

- private PHP methods;
- internal React state;
- exact internal function-call order;
- implementation details not observable to the user.

## Stability

E2E tests must be deterministic.

Avoid:

- arbitrary `sleep`;
- fixed delays;
- test-order dependence;
- production services;
- uncontrolled external APIs;
- unstable selectors;
- shared random state.

Prefer waiting for observable conditions.

## Infrastructure failure

If browser automation, React, Laravel, MySQL, or another required E2E dependency cannot start:

```text
NOT_EXECUTED
```

Never report E2E as passed when it did not actually run.

## Coverage

E2E is a **workflow verification gate**, not the primary coverage source.

Do not require E2E to contribute to the main PHP or React coverage target.

For this project:

```text
Backend coverage
→ Unit + Integration

Frontend coverage
→ React Unit / component tests

E2E
→ complete-flow verification
```

E2E may technically produce coverage with additional instrumentation, but that is not required by the default project strategy.

## Relationship between levels

```text
Unit
→ backend application/use cases
→ business rules
→ exhaustive backend input validation
→ React components/pages/hooks/forms
→ frontend validation and UI states

Integration
→ backend controllers
→ real Laravel request flow
→ real MySQL database
→ persistence / transactions / constraints / concurrency
→ other real backend dependencies when required

E2E
→ real application route
→ real browser
→ real React / Inertia
→ real Laravel
→ real MySQL
→ selected complete user workflows
```

This project does not require a separate React integration-test category by default.

## Existing E2E tests are contracts

Do not:

- remove a valid E2E test because production code broke it;
- weaken assertions only to make it pass;
- add arbitrary waits to hide race conditions;
- skip critical flows without justification;
- mock the real stack only to avoid fixing integration problems.

## CI/CD acceptance rules

E2E verification is a workflow gate.

When a task requires E2E coverage according to this document, the task is not fully verified until the required E2E scenarios execute and pass.

The E2E gate must satisfy all of the following:

- the real browser/application entry point is exercised;
- the real React/Inertia frontend is used;
- the real Laravel application is used;
- the real application routes are used;
- the real MySQL test database is used;
- required authentication/session behavior works;
- required user-visible flows complete successfully;
- expected persisted effects are visible through the application;
- representative backend validation is correctly displayed by React when relevant;
- no required E2E scenario is skipped without an approved reason;
- lower-level required unit and integration gates also pass.

### When E2E is required

E2E should be required for selected complete flows, especially when a change affects:

- a critical administrator workflow;
- authentication/session behavior;
- an important create/edit/delete flow;
- React ↔ Laravel integration visible to the user;
- redirects/navigation across the stack;
- behavior that cannot be fully proven at unit or integration level.

E2E is not required merely to duplicate lower-level tests.

### Coverage

E2E is not part of the default PHP or React coverage threshold.

For this project:

```text
Backend coverage
→ Unit + Integration when collected by CI/CD

Frontend coverage
→ React Unit / component tests

E2E
→ complete-flow verification
```

### Incomplete verification

E2E verification is incomplete when:

- the browser environment cannot start;
- React or Laravel cannot start;
- the MySQL test database is unavailable;
- a required workflow does not execute;
- the real stack is replaced with mocks for the workflow being verified.

If E2E is required for the task and was not executed, report:

```text
NOT_EXECUTED
```

Never infer E2E success from unit tests, backend integration tests, or successful builds.

## Final rule

Before creating an E2E test, answer:

**Which real route and complete React ↔ Laravel ↔ MySQL workflow does this test prove?**

If the behavior can be fully protected by unit or backend integration tests, do not add E2E only for duplication.
