# Validation

Execute implemented the approved login-card width fix. `[data-layout="login-card"]` now uses `w-full max-w-6xl xl:max-w-7xl` and keeps the existing `lg` 46/54 grid. No extra product files were required beyond the planned set.

## Acceptance Criteria

| AC | Spec-defined outcome | Evidence | Covered? |
| --- | --- | --- | --- |
| AC-001 / LOGIN-01 | Card has `w-full` so it uses the padded viewport instead of shrinking to form content | `Login.test.jsx:270` `expect(card.className).toMatch(/\bw-full\b/)` on `[data-layout="login-card"]`. Product: `Login.jsx:56` | Yes |
| AC-002 / LOGIN-02 | At Tailwind `lg` or wider, card uses `max-w-6xl` and `lg:grid-cols-` 46/54 | `Login.test.jsx:271` `toMatch(/\bmax-w-6xl\b/)`; `:274` `toMatch(/lg:grid-cols-/)`; `:275-276` `toMatch(/46%/)` and `toMatch(/54%/)`. Product: `Login.jsx:56` | Yes |
| AC-003 / LOGIN-03 | At Tailwind `xl` or wider, card uses `xl:max-w-7xl` | `Login.test.jsx:272` `expect(card.className).toMatch(/\bxl:max-w-7xl\b/)`. Product: `Login.jsx:56` | Yes |
| Edge: `lg`–`xl` stays at `max-w-6xl`, not `max-w-5xl` | Between `lg` and `xl` the cap is `max-w-6xl`, not the old `max-w-5xl` | `Login.test.jsx:271` `toMatch(/\bmax-w-6xl\b/)`; `:273` `not.toMatch(/\bmax-w-5xl\b/)`. `xl:max-w-7xl` is the `xl` override only | Yes |
| AC-004 / LOGIN-04 | Below `lg`, hero stays `hidden` and the form still shows ReservaSalas | `Login.test.jsx:277` `hero.className` `toMatch(/hidden/)`; `:278` `toMatch(/lg:flex/)`; `:283-284` form `toContain('Reserva')` and `toContain('Salas')` | Yes |
| AC-005 / LOGIN-05 | Shell keeps `px-6` and `overflow-x-hidden`; `Entrar` stays `w-full` | `Login.test.jsx:279` Entrar `toMatch(/w-full/)`; `:280` shell `toMatch(/\bpx-6\b/)`; `:281` shell `toMatch(/overflow-x-hidden/)`. Product: `Login.jsx:53`, `Login.jsx:102` | Yes |
| AC-006 / LOGIN-06 | Heading `Acesse sua conta`, supporting text, email/password fields; no cadastro or password recovery | `Login.test.jsx:46` heading `Acesse sua conta`; `:47` supporting text; `:57-64` E-mail and Senha fields; `:81-84` no cadastro / `/register`; `:90-91` no esqueci/recuper | Yes |

Screen doc Responsiveness names the same tokens: `screen-login.md:195` `w-full`, `max-w-6xl` (72rem), `xl:max-w-7xl` (80rem); `:196` 46/54 at `lg`; `:202` one-column `w-full` below `lg`. Auth, validation, and accessibility sections were not rewritten.

No extra product files. Local `.env` was created from `.env.example` only to run artisan in this worktree and is gitignored.

## Test Results

Unit (Vitest, `docs/test/unit.md`): CSS class contracts are the layout proof. The existing layout case was extended; heading / field / no-cadastro / no-recovery cases were kept. Test count: 115 passed (17 in `Login.test.jsx`, same case count as before plus stronger assertions). Coverage: statements 96.28% (2047/2126), above 80%.

`harness.tests.unit` mapping:

1. Card `w-full` — `Login.test.jsx:270`
2. `max-w-6xl` and `lg:grid-cols-` 46/54 — `Login.test.jsx:271,274-276`
3. `xl:max-w-7xl` — `Login.test.jsx:272`
4. Hero `hidden` / `lg:flex`; form ReservaSalas — `Login.test.jsx:277-278,283-284`
5. Shell `px-6` + `overflow-x-hidden`; Entrar `w-full` — `Login.test.jsx:279-281`
6. Heading and no cadastro / recovery — `Login.test.jsx:46-47,81-84,90-91`

Integration: not applicable. No Laravel route, FormRequest, controller, session, or MySQL change. Existing Feature login suite stayed green (126 passed).

E2E: not applicable. No Playwright project. Layout is a React class contract covered by Vitest. `docs/test/e2e.md` forbids repeating that matrix in the browser.

PHP Unit: 70 passed (337 assertions). `--coverage --min=80` exit 0.

Tests modified: the one existing layout case only (assertions added, none deleted or weakened). Tests skipped/deleted: none.

## Required Gates

Local worktree runs before `complete-phase`. The harness re-runs these gates after Execute. This report does not declare the harness gate final.

| Gate | Command | Local result |
| ---- | ------- | ------------ |
| unit | `php artisan test --testsuite=Unit --coverage --min=80` | 70 passed, exit 0 |
| frontend | `npm run test:coverage` | 115 passed, 96.28% statements |
| integration | `php artisan test --testsuite=Feature` | 126 passed, exit 0 |
| lint | `vendor/bin/pint --test` | passed |
| frontend_lint | `npm run lint` | passed |
| php_build | `composer run build` | passed (caches cleared afterward) |
| frontend_build | `npm run build` | passed |

Playwright was not run.

## Review Result

Pending. Review has not run. No `review/review-NN.md` exists for this execute round.

## Final Status

EXECUTE complete for task 0026. T1 and T2 done. Product files: `resources/js/Pages/User/Login.jsx`, `resources/js/Pages/User/Login.test.jsx`, `docs/screens/screen-login.md`. Spec traceability LOGIN-01..06 marked Done. Awaiting harness deterministic gates and human review. No commit from Execute.

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
<!-- harness-checks:end -->
