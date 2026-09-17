# Test Review

Review whether new or changed behavior is protected at the correct test level and according to the project's documented testing strategy.

This reviewer does **not define the testing strategy**.

It verifies whether the existing strategy was followed.

## Sources of truth

The test review must use the following documents as its sources of truth:

- `docs/test/unit.md`
- `docs/test/integration.md`
- `docs/test/e2e.md`

Each document defines the criteria, boundaries, responsibilities, and expectations of its respective test level.

The reviewer must **not create its own test matrix** and must not classify a test level only from the type of production file that changed.

When reviewing:

- use `docs/test/unit.md` to evaluate unit tests;
- use `docs/test/integration.md` to evaluate integration tests;
- use `docs/test/e2e.md` to evaluate E2E tests;
- confirm that the selected level matches the boundaries actually exercised;
- verify that tests required by those documents were implemented;
- report tests placed at the wrong level;
- report unnecessary duplication between levels;
- verify that existing valid tests were preserved;
- do not require tests that the source documents do not justify;
- do not replace required integration coverage with mocks or artificial unit tests;
- do not turn every behavior into an E2E test.

If the correct level remains ambiguous after consulting all three documents, report the uncertainty instead of inventing a classification.

## Review priorities

Review in this order:

1. relevant behavior is protected;
2. correct test level;
3. important error scenarios;
4. regression protection;
5. determinism;
6. isolation;
7. readability;
8. coverage.

Coverage does not replace test quality.

## General checks

Report:

- new behavior without required tests;
- changed behavior without corresponding protection;
- a required integration test replaced only by mocks;
- changed external contract without appropriate test coverage;
- relevant error scenario missing;
- regression protection removed or weakened;
- artificial test created only for coverage;
- tests coupled to implementation details;
- non-deterministic tests;
- shared mutable test state;
- order-dependent tests;
- uncontrolled time or randomness;
- `sleep()` used for synchronization when a deterministic alternative exists;
- mocks used outside appropriate boundaries;
- tests of private methods;
- tests of framework/library behavior instead of project behavior.

When a correct existing test fails after a production change, the default assumption is:

**the new implementation is wrong.**

Do not silently adapt the test to the implementation.

## PHP / Laravel tests

Evaluate PHP/Laravel tests according to `docs/test/unit.md`, `docs/test/integration.md`, and `docs/test/e2e.md`.

Depending on the documented test level, check for:

- relevant isolated logic covered by unit tests;
- real Eloquent/database behavior covered where integration is required;
- transactions, constraints, locks, and concurrency tested at the level required by `docs/test/integration.md`;
- HTTP behavior covered when required by `docs/test/e2e.md`;
- factories that keep test setup understandable;
- the project's approved database reset/isolation strategy;
- assertions on observable behavior;
- authentication and authorization behavior covered at the appropriate level;
- Laravel fakes used only when they do not hide the behavior that should be exercised.

Report:

- tests that only call a method and assert a trivial value without protecting behavior;
- tests of Laravel internals;
- mocks replacing the rule being tested;
- a different database engine used when that invalidates the behavior being verified;
- `Event::fake()`, `Queue::fake()`, or similar fakes hiding the exact behavior the test is supposed to prove.

## React / TypeScript tests

Evaluate frontend tests according to the same source documents and the boundaries they define for the frontend.

Check, when applicable:

- component behavior;
- user interaction;
- relevant conditional rendering;
- form behavior;
- loading, error, empty, and success states;
- behavior of custom hooks;
- integration with the HTTP/data layer when required;
- observable accessibility behavior;
- regressions in user-visible behavior;
- contract changes between React and the backend.

Prefer tests from the user's perspective when that matches the documented level.

Report tests that:

- inspect internal component state instead of observable behavior;
- depend on implementation details of hooks;
- test React itself instead of project behavior;
- use huge snapshots without a clear purpose;
- mock so much that no real behavior remains;
- depend on fragile CSS/DOM structure when a semantic selector is available.

## React Testing Library

When React Testing Library is used by the project, prefer selectors close to user behavior:

```text
role
label
text
```

Avoid depending unnecessarily on:

```text
CSS class
internal DOM structure
data-testid everywhere
```

`data-testid` is acceptable when no suitable semantic selector exists.

## Mocking

Mocks must respect the boundaries defined in `docs/test/unit.md`, `docs/test/integration.md`, and `docs/test/e2e.md`.

They may be appropriate for boundaries such as:

- external API;
- clock;
- storage;
- browser API;
- external service;
- infrastructure that the selected test level intentionally replaces.

Do not mock the unit whose behavior is being tested.

Excessive mocking can produce a green test that proves nothing meaningful.

## PHP ↔ React contract tests

When a task changes the backend/frontend contract, verify that the appropriate level protects the observable contract.

Examples include:

- field names;
- value types;
- validation errors;
- HTTP status codes;
- redirects;
- Inertia props;
- required and optional properties.

Do not duplicate every business rule in both frontend and backend tests.

Protect the observable responsibility of each boundary.

## Existing tests are contracts

Do not allow:

- deleting a valid test to hide a regression;
- reducing assertions only to make the suite pass;
- unjustified skip;
- changing the expected result without an approved behavior change;
- adding a mock only to bypass the failure;
- rewriting a correct scenario to match incorrect production code.

For a bug fix, prefer a regression test that fails before the fix and passes after it when the source test documents justify that level.

## Coverage

Use the coverage rules defined by the project's testing documents or other approved project policy.

Coverage below the configured threshold requires analysis, but percentage alone does not determine severity.

Do not create:

- trivial tests;
- redundant tests;
- getter/setter tests;
- framework tests;

only to increase coverage.

## Severity

```text
❌ Blocker — missing or invalid testing allows a severe security or integrity regression
⚠️ High    — relevant behavior is not adequately protected
📝 Medium  — lower-risk gap or test-quality problem
```

Severity must follow the actual risk and the requirements defined in the source test documents.

## Review output

For each issue, report:

- affected file or test;
- applicable source document and rule;
- behavior that should be protected;
- problem;
- expected test level;
- recommended correction.

Do not invent test requirements that are not supported by `docs/test/unit.md`, `docs/test/integration.md`, or `docs/test/e2e.md`.

When no relevant test issue is found, state that the reviewed behavior is adequately protected and mention only areas that could not be verified.

The reviewer verifies the testing strategy defined by the project. It does not redefine that strategy.
