# Code Smell Review

Review code clarity, complexity, typing, duplication, maintainability, and language/framework-specific smells.

A code smell is not automatically a bug.

Classify findings by real impact, not by the mere presence of a language feature.

Do not turn style preferences into review findings.

## General checks

Report when relevant:

- functions or methods with unrelated responsibilities;
- excessive nesting;
- difficult-to-follow control flow;
- too many parameters;
- multiple boolean parameters with unclear meaning;
- significant duplication;
- vague or misleading names;
- hidden mutable state;
- surprising side effects;
- dead code;
- comments used to explain unnecessarily confusing code;
- premature abstraction;
- optimization without evidence;
- generic functions such as `process`, `handle`, `execute`, or `manage` without a clear responsibility;
- broad helpers or utility modules that become dumping grounds;
- speculative code for future features not required by the task.

## PHP smells

Report when unjustified:

- `mixed` used where a known type could be expressed;
- untyped parameters when the type is known;
- missing return types when the contract is known;
- `array<string, mixed>` used as a permanent contract;
- deeply nested arrays without a clear structure;
- `stdClass` used for known structures;
- dynamic properties;
- widespread casts used to compensate for weak contracts;
- loose comparison (`==`, `!=`) where coercion can change behavior;
- implicit type juggling with meaningful behavioral consequences;
- the `@` operator suppressing errors;
- empty `catch` blocks;
- broad `catch (\Throwable)` or `catch (\Exception)` hiding failures;
- custom magic methods without a concrete reason;
- mutable global or static state;
- functions whose behavior changes radically based on loosely related arguments;
- repeated manual normalization that suggests a missing explicit contract.

`mixed` is not forbidden.

It is acceptable when the value is genuinely dynamic or required by a framework boundary.

Do not use `mixed` only to avoid expressing a known contract.

## Laravel smells

Report when relevant:

- `$request->all()` propagated indiscriminately;
- uncontrolled data passed to `create()` or `fill()`;
- `$guarded = []` without a clear justification;
- overly large controllers;
- Models accumulating unrelated behavior only to make controllers smaller;
- queries inside loops;
- N+1 queries;
- `Model::all()` in potentially large flows without need;
- `DB::raw()` hiding an otherwise simple implementation;
- duplicated validation without a concrete reason;
- the same important query or rule copied across multiple places;
- global helpers holding application rules;
- Facades used in ways that create harmful hidden state or testing difficulty;
- events, listeners, or observers hiding a simple important flow;
- important behavior hidden in model events;
- `env()` used outside configuration files;
- `optional()` or null-safe access used only to hide invalid states;
- indiscriminate `first()` / `firstOrFail()` without considering the expected contract;
- manual framework reimplementation without a project-specific reason.

Idiomatic Laravel features are not smells simply because they exist.

## TypeScript smells

Report:

- `any` without a concrete need;
- `unknown` propagated without narrowing;
- `as` used only to silence the compiler;
- chained assertions such as `as unknown as T`;
- `@ts-ignore` without a strong justification;
- `@ts-expect-error` without a documented and intentional reason;
- `Record<string, any>` where a known contract exists;
- component props without clear types;
- hook return values without a comprehensible contract;
- overly broad unions;
- plain `string` where a known closed set should be represented explicitly;
- non-null assertions (`!`) used to hide an unhandled state;
- duplicate frontend types that drift from the actual API contract;
- type assertions replacing validation of external data.

`any` should be treated as a smell similar to `mixed` in PHP: acceptable at genuinely dynamic boundaries, but not as a shortcut around known contracts.

## React smells

Report when relevant:

- `useEffect` used to calculate derivable state;
- `useEffect` with incorrect or intentionally suppressed dependencies;
- one effect owning several unrelated behaviors;
- redundant state;
- props copied into state without a real reason;
- excessive prop drilling when the project already has an appropriate boundary;
- Context used for trivial local state;
- oversized Context providers with unrelated responsibilities;
- components with too many responsibilities;
- overly generic components without actual reuse;
- hooks containing unrelated behaviors;
- hooks called conditionally;
- direct mutation of state;
- unstable list keys when stable identity exists;
- premature `useMemo` or `useCallback`;
- complex nested JSX conditionals;
- duplicated request/loading/error handling when the project already defines a pattern;
- data fetching scattered across arbitrary components against the project convention.

Do not report `useMemo`, `useCallback`, Context, or custom hooks merely because they exist.

## AI-generated code smells

Pay special attention to:

- abstraction without current usage;
- generic helper modules;
- unnecessary wrappers;
- dead code;
- extension points for hypothetical future needs;
- needless indirection;
- patterns introduced by habit rather than requirement;
- comments explaining unnecessary complexity created by the implementation itself.

## Prohibited or severe smells

### PHP / Laravel

Always report:

- `@` hiding a relevant failure;
- empty `catch`;
- secret hardcoded in source;
- insecure SQL construction;
- deliberately unsafe mass assignment;
- mutable global state used as normal application state;
- `mixed` used to hide a critical known contract.

### React / TypeScript

Always report:

- unjustified `any` in an important contract;
- `@ts-ignore` used to avoid fixing a real error;
- unsafe assertion used to lie to the compiler;
- conditional hook calls;
- direct React state mutation;
- real secrets included in the frontend bundle.

## Severity

```text
❌ Blocker — smell directly creates a serious bug, vulnerability, or integrity failure
⚠️ High    — relevant correctness or maintainability problem
📝 Medium  — real smell without immediate critical impact
```

Do not turn an isolated smell into a Blocker without explaining its consequence.

## Review output

For each issue, report:

- affected file or section;
- smell;
- impact;
- recommended correction.

Do not invent findings to fill the review.

Do not request a large refactor when a small correction addresses the problem.

When no relevant code smell is found, state that clearly.
