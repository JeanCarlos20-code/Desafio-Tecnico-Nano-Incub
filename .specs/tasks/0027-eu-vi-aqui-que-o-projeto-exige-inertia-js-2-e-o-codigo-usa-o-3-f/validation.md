# Validation

Execute pinned both Inertia adapters to `^2.0` (locks: `@inertiajs/react` / `@inertiajs/core` 2.3.28, `inertiajs/inertia-laravel` v2.0.27) and remapped v3 visit callbacks onto per-visit `router.on('invalid'|'exception')`. Product JavaScript no longer passes `onHttpException` or `onNetworkError`. PHP `Inertia::render`, `HandleInertiaRequests`, and `createInertiaApp` were not edited.

## Acceptance Criteria

| AC | Spec-defined outcome | Evidence | Covered? |
| --- | --- | --- | --- |
| AC-001 / INERTIA-01 | `@inertiajs/react` is `^2.0`, not a 3.x range | `package.json:33` `"@inertiajs/react": "^2.0"`. Lock root `package-lock.json` `packages[""].dependencies` is `^2.0` | Yes (manifest; `docs/test/unit.md` forbids a config-only unit test) |
| AC-002 / INERTIA-02 | `inertiajs/inertia-laravel` is `^2.0`, not a 3.x range | `composer.json:10` `"inertiajs/inertia-laravel": "^2.0"` | Yes (manifest) |
| AC-003 / INERTIA-03 | Locks install React, core, and inertia-laravel 2.x only | `package-lock.json` `node_modules/@inertiajs/react` version `2.3.28`; `node_modules/@inertiajs/core` version `2.3.28`; `composer.lock` `inertiajs/inertia-laravel` version `v2.0.27` | Yes (lockfiles + `npm run build` / `composer run build`) |
| AC-004 / INERTIA-04 | Product JS does not pass `onHttpException` or `onNetworkError` as visit options | `session.test.js:75-76` `expect(...onHttpException).toBeUndefined()` and `onNetworkError`. `Reservation/Index.test.jsx:514-515` same on retry visit options. Product pages/services use `onInvalid` / `onException` only | Yes |
| AC-005 / INERTIA-05 | Non-Inertia response uses `router.on('invalid')` and `preventDefault` when the handler returns `false` | `inertiaVisit.test.js:22` `toHaveBeenCalledWith('invalid', expect.any(Function))`; `:31` `expect(event.preventDefault).toHaveBeenCalledTimes(1)` | Yes |
| AC-006 / INERTIA-06 | Unexpected XHR/network error uses `router.on('exception')` and `preventDefault` when the handler returns `false` | `inertiaVisit.test.js:39` `toHaveBeenCalledWith('exception', expect.any(Function))`; `:48` `expect(event.preventDefault).toHaveBeenCalledTimes(1)` | Yes |
| AC-007 / INERTIA-07 | Login, room save, reservation save, cancel, and list retry keep the existing Portuguese copy and stay on the screen | Login `Login.test.jsx:263` preventDefault; `:265` `Não foi possível entrar. Tente novamente.`; `:267` heading still present. Room `Create.test.jsx:184` preventDefault; `:186` `Não foi possível salvar a sala. Tente novamente.`. Reservation create `Create.test.jsx:198` `Não foi possível salvar a reserva. Tente novamente.`. Cancel `Index.test.jsx:544-546` preventDefault true; `:546` dialog still present; `:547` cancel error. Retry `Index.test.jsx:512-513` preventDefault; `:516` `Não foi possível carregar as reservas.` | Yes |
| Edge: omitted login return is `false` | `session.js` still treats a missing page return as `false` (prevent default) | `session.test.js:73-74` `expect(invalidEvent.preventDefault).toHaveBeenCalledTimes(1)` and the exception event the same | Yes |
| Edge: both handlers omitted | Helper does not register listeners | `inertiaVisit.test.js:83` `expect(router.on).not.toHaveBeenCalled()`; `:84` `expect(visit).toBe(options)` | Yes |
| Edge: existing `onFinish` | Unsubscribe first, then call the original `onFinish` | `inertiaVisit.test.js:71-74` unsubscribe called; `onFinish` called with `'visit'`; unsubscribe invocation order is before `onFinish` | Yes |

## Test Results

Unit (Vitest, `docs/test/unit.md`): Inertia is mocked. New helper cases plus renamed existing failure cases. Test count: 119 passed (was 115; +4 in `inertiaVisit.test.js`). Coverage: statements 96.35% (2086/2165), above 80%.

`harness.tests.unit` mapping:

1. Helper `invalid` + `preventDefault` on `false` — `inertiaVisit.test.js:22,31`
2. Helper `exception` + `preventDefault` on `false` — `inertiaVisit.test.js:39,48`
3. Helper unsubscribes on `onFinish` and still calls the original — `inertiaVisit.test.js:71-74`
4. session login omitted return is `false` — `session.test.js:73-74`
5. Login banner `Não foi possível entrar. Tente novamente.` — `Login.test.jsx:265`
6. Room/Create banner `Não foi possível salvar a sala. Tente novamente.` — `Create.test.jsx:186`
7. Reservation/Create banner `Não foi possível salvar a reserva. Tente novamente.` — `Create.test.jsx:198`
8. Reservation/Index cancel unexpected failure returns false, keeps dialog, shows cancel error — `Index.test.jsx:544-547`
9. Reservation/Index retry unexpected failure returns false and keeps load-failure copy — `Index.test.jsx:512-516`

Integration: not applicable. No new Laravel route, middleware, FormRequest, controller, or MySQL behavior. Existing Feature suite stayed green (126 passed). See `harness.tests_not_applicable.integration`.

E2E: not applicable. No Playwright project. Unexpected-failure banners are React/service contracts covered by Vitest. `docs/test/e2e.md` forbids repeating that matrix in the browser.

PHP Unit: 70 passed (337 assertions). `--coverage --min=80` exit 0.

Tests skipped/deleted: none. Existing failure-banner cases were updated to fire v2 `invalid` / `exception` listeners instead of v3 option names.

Extra files (concrete dependency): `Room/Edit.test.jsx`, `Reservation/Edit.test.jsx`, and `Room/Index.test.jsx` gained `router.on` on the existing `@inertiajs/react` mock so submit/retry paths that wrap `onInvalid` / `onException` can subscribe. `.env` was created from `.env.example` only to run artisan in this worktree and is gitignored.

## Required Gates

Local worktree runs before `complete-phase`. The harness re-runs these gates after Execute. This report does not declare the harness gate final.

| Gate | Command | Local result |
| ---- | ------- | ------------ |
| unit | `php artisan test --testsuite=Unit --coverage --min=80` | 70 passed, exit 0 |
| frontend | `npm run test:coverage` | 119 passed, 96.35% statements |
| integration | `php artisan test --testsuite=Feature` | 126 passed, exit 0 |
| lint | `vendor/bin/pint --test` | passed |
| frontend_lint | `npm run lint` | passed |
| php_build | `composer run build` | passed |
| frontend_build | `npm run build` | passed |

Playwright was not run.

## Review Result

Pending. Review has not run. No `review/review-NN.md` exists for this execute round.

## Final Status

Ready for harness Execute checks and human review. No product commit in this phase.

<!-- harness-checks:start -->
## Harness deterministic checks

- ✅ `php artisan test --testsuite=Unit --coverage --min=80` — exit=0 (required)
- ✅ `npm run test:coverage` — exit=0 (required)
- ✅ `php artisan test --testsuite=Feature` — exit=0 (required)
- ✅ `vendor/bin/pint --test` — exit=0 (required)
- ✅ `npm run lint` — exit=0 (required)
- ✅ `composer run build` — exit=0 (required)
- ✅ `npm run build` — exit=0 (required)
- ✅ `php artisan test && npm run test` — exit=0 (required)
- ✅ `vendor/bin/pint --test && npm run lint` — exit=0 (required)
- ✅ `composer run build && npm run build` — exit=0 (required)
- ✅ `python3 -m pytest tests -q` — exit=0 (required)
- ✅ `python3 -m compileall -q src` — exit=0 (required)
- ✅ `PYTHONPATH=src python3 -c "import project_harness"` — exit=0 (required)
<!-- harness-checks:end -->
