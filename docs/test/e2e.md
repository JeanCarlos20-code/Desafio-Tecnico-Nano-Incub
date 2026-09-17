# End-to-End Testing Strategy

End-to-end tests verify the application as a complete system from the user's perspective.

For this project, E2E means exercising the real frontend and backend together through a browser.

The complete flow is:

```text
browser
→ React / Inertia
→ Laravel routes and middleware
→ validation
→ controller
→ use case / application behavior
→ MySQL test database
→ response
→ rendered React UI
```

E2E tests prove that the complete application works together.

They do not replace unit or integration tests.

## Source of truth

This document defines what belongs to the **E2E** test level.

The test reviewer must use this document when evaluating E2E coverage.

## Scope

E2E tests are reserved for complete user-visible flows and critical cross-stack contracts.

Use E2E for workflows that genuinely require React, Laravel, MySQL, and browser behavior together.

Examples:

- opening the application;
- authentication through the real UI;
- protected-page access;
- creating data through a real form;
- editing data through the real UI;
- deleting or cancelling through the real UI;
- backend validation displayed in React;
- persistence visible after navigation or reload;
- redirects or navigation across the real stack;
- filters or searches that cross frontend and backend.

## Real stack

E2E should use, as applicable:

- real browser automation;
- real React application;
- real Inertia integration;
- real Laravel application;
- real routes and middleware;
- real MySQL test database.

Do not mock:

- Laravel backend;
- MySQL;
- React pages involved in the flow.

External third-party services may use approved test doubles or sandbox environments when the project explicitly defines that boundary.

## Which flows need E2E

E2E coverage should be selective.

Create E2E tests for:

- critical administrator workflows;
- important create/edit/delete flows;
- authentication/session flows;
- high-value flows crossing React, Laravel, and MySQL;
- important regressions that cannot be adequately protected at a lower level;
- important backend/frontend integration contracts.

Do not create an E2E test for every validation rule.

Do not repeat every controller integration scenario through the browser.

Do not repeat every React component unit test in E2E.

## Validation

E2E should test representative validation behavior only.

Example:

1. submit invalid data through the real React form;
2. Laravel rejects the request;
3. React displays the real validation error;
4. invalid data is not persisted.

The exhaustive validation matrix belongs to `unit.md`.

Controller wiring and database-dependent validation belong to `integration.md`.

## Authentication and authorization

When authentication or protected access is part of a critical workflow, E2E should exercise the real behavior.

Examples:

- unauthenticated access is denied or redirected;
- administrator can log in;
- authenticated session persists as expected;
- protected page becomes accessible;
- logout removes access.

Frontend visibility alone is never proof of backend authorization.

## React behavior in E2E

Use E2E when React behavior only becomes meaningful with the real backend.

Examples:

- Laravel errors appear in the real form;
- backend redirect reaches the expected React page;
- persisted data appears after navigation;
- filter behavior works through UI and backend;
- update/delete actions change both database state and visible UI;
- session state changes the real rendered application.

Component-only behavior belongs to `unit.md`.

## Database isolation

Never use production data or credentials.

Use an isolated E2E database.

Each scenario must start from a known state.

Tests must not depend on execution order.

Use the reset/seed strategy defined by the project.

## Assertions

Assert user-visible outcomes and important persisted effects.

Good assertions include:

- expected page or element is visible;
- expected validation message appears;
- navigation reaches the correct page;
- successful action is visible;
- persisted data remains after reload;
- deleted/cancelled state is reflected in the UI;
- unauthorized user cannot complete the action.

Avoid assertions about:

- private PHP methods;
- internal React state;
- exact internal function-call order;
- implementation details not visible to the user.

## Stability

E2E tests must be deterministic.

Avoid:

- arbitrary `sleep`;
- fixed delays;
- test-order dependence;
- production services;
- uncontrolled external APIs;
- unstable selectors;
- shared random data.

Prefer waiting for observable conditions.

## Failure of required infrastructure

If the browser, frontend, Laravel application, MySQL database, or another required dependency cannot start:

```text
NOT_EXECUTED
```

Never report E2E as passed when it did not run.

## Relationship between levels

Use the levels as follows:

```text
Unit
→ backend use cases / business rules
→ exhaustive backend input validation
→ React components/pages/hooks/forms in isolation
→ frontend validation and UI states

Integration
→ backend controllers
→ real Laravel request flow
→ real MySQL database
→ persistence / transactions / constraints / concurrency

E2E
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

## Final rule

Before creating an E2E test, answer:

**Which complete user workflow or React ↔ Laravel ↔ MySQL contract requires the real stack to be proven?**

If the behavior can be fully protected by unit or backend integration tests, do not add E2E only for duplication.
