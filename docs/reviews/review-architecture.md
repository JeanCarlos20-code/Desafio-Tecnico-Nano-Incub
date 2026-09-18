# Architecture Review

Review structural decisions, responsibilities, dependencies, boundaries, and compliance with the project's documented architecture.

Do not review code style, isolated security vulnerabilities, or test coverage in this document unless they reveal an architectural problem.

Do not impose architectural preferences that are not defined by the project.

## Sources of truth

The architecture review must use the following documents as its sources of truth:

- `docs/context.md`
- `docs/architecture.md`
- `docs/tree.md`

These documents define the project context, architectural decisions, expected boundaries, and project structure.

The reviewer must **not create new architectural rules** or replace documented decisions with personal preferences.

When reviewing:

- use `context.md` to validate scope, project constraints, and implementation principles;
- use `architecture.md` to validate architectural decisions, responsibilities, dependencies, and technical conventions;
- use `tree.md` to validate file placement, directories, module ownership, and structural organization;
- report divergence between implementation and documentation;
- do not require patterns, layers, or abstractions that are not documented or justified by the current task;
- do not silently reinterpret or replace an existing decision.

If the documents do not define a situation, treat it as an open decision and evaluate the implementation by simplicity, consistency, and compatibility with the rest of the project.

## Review priorities

Review in this order:

1. compliance with documented architecture;
2. responsibility boundaries;
3. dependency direction and coupling;
4. project structure and file ownership;
5. clarity of important flows;
6. unnecessary abstraction;
7. maintainability;
8. simplicity.

Prefer the smallest architecture that correctly solves the current problem.

## General architecture checks

Report:

- components with unrelated responsibilities;
- inappropriate dependencies;
- circular dependencies;
- duplicated business decisions across multiple places;
- important flows hidden behind side effects;
- abstractions without a concrete benefit;
- layers introduced only by convention;
- premature generalization;
- code prepared for features that were not requested;
- large architectural rewrites when a localized change would be sufficient;
- implementation that contradicts `context.md`, `architecture.md`, or `tree.md`.

Do not require:

- Repository;
- Service;
- Action;
- DTO;
- Use Case;
- Value Object;
- interface abstractions;

unless the project documents or the current problem justify them.

## PHP / Laravel

When the reviewed area is backend code, check for:

- controllers with excessive responsibilities;
- Eloquent Models accumulating unrelated responsibilities;
- Form Requests making decisions outside input validation;
- important behavior hidden in observers or model events;
- application behavior placed in `AppServiceProvider`;
- global helpers used as a container for business logic;
- generic Services without a clear responsibility;
- manual dependency construction that contradicts the project's dependency strategy;
- transactions started in a layer that does not own the real consistency boundary;
- database or infrastructure details leaking into areas that should not own them;
- framework functionality reimplemented without a documented reason.

Prefer idiomatic Laravel when it satisfies the documented architecture.

Do not turn a small Laravel application into an enterprise architecture without a concrete project requirement.

## React / TypeScript

When the reviewed area is frontend code, check for:

- components with unrelated responsibilities;
- presentation components owning application rules;
- relevant logic duplicated across pages or components;
- HTTP access scattered across the application when the project defines a dedicated boundary;
- global state introduced for data that should remain local;
- local state duplicating values that can be derived;
- components tightly coupled to transport details;
- generic hooks without a clear responsibility;
- Context used indiscriminately instead of local state or explicit props;
- global providers without a concrete need;
- large components mixing data loading, transformation, state, behavior, and presentation;
- premature component abstractions without actual reuse or a clear conceptual boundary.

Do not split components only to reduce line count.

Extract components when there is a clear responsibility, reuse case, or meaningful UI/behavior boundary.

## PHP ↔ React boundary

Check for:

- inconsistent contracts between backend and frontend;
- the same contract manually redefined in multiple places without a project-approved reason;
- frontend code compensating for incorrect backend behavior;
- business invariants enforced only in React;
- backend code depending on purely visual frontend concerns;
- contract changes implemented on only one side;
- undocumented changes to field names, types, status codes, redirects, or Inertia props.

The backend remains responsible for protecting business invariants.

The frontend may repeat validation for user experience, but frontend validation never replaces backend enforcement.

## AI-generated architecture smells

Report code introduced without a concrete need, including:

- unused abstraction;
- interface with no useful boundary;
- generic helper;
- framework wrapper without a reason;
- dead code;
- speculative extension points;
- design patterns introduced only because they are common;
- future-oriented architecture not required by the task.

## Severity

```text
❌ Blocker — violates a mandatory architectural rule or creates direct integrity risk
⚠️ High    — relevant architectural problem that should be corrected
📝 Medium  — real architectural smell or maintainability concern
```

Do not classify a preference as an architectural violation.

## Review output

For each issue, report:

- affected file or section;
- source document and rule involved;
- problem;
- impact;
- recommended correction.

Do not propose a large refactor when a localized correction is sufficient.

When no relevant architectural issue is found, state that clearly and mention only risks that could not be verified.

The reviewer verifies the architecture defined by the project. It does not redesign the project based on personal preference.
