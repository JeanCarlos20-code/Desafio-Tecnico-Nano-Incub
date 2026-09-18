# Validation

Execute implemented T1–T3 against the approved spec. Tailwind CSS v4 stayed on `@tailwindcss/vite`. Register is a full-viewport flex shell. Reservations uses `AppLayout`. No commit from this phase.

## Acceptance Criteria

| AC | Requirement | Evidence | Result |
| -- | ----------- | -------- | ------ |
| AC-001 / UI-01 | Register page shell is a Tailwind flex container that fills the viewport and centers `[data-layout="register-card"]` | `Create.test.jsx:292-299` — `expect(shell.className).toMatch(/\bflex\b/)`, `min-h-dvh\|min-h-screen`, `items-center`, `justify-center-safe\|justify-center`, `shell.contains(card)` | PASS |
| AC-002 / UI-02 | Tall card stays reachable (no clipped top) | `Create.test.jsx:306` — `expect(container.firstChild.className).toMatch(/justify-center-safe/)` (`safe center` aligns to start when space is insufficient) | PASS |
| AC-003 / UI-03 | ≥24px horizontal padding and `overflow-x-hidden` | `Create.test.jsx:296-297` — `overflow-x-hidden`, `px-6` (24px) | PASS |
| AC-004 / UI-04 | `User/Create` styled only with Tailwind utilities | `Create.jsx:46` shell and card use utility `className` only; no CSS module or second framework added | PASS |
| AC-005 / UI-05 | Wide viewport: ~44%/56% two-column grid + brand hero | `Create.test.jsx:274-277` — `lg:grid-cols-[minmax(0,44%)_minmax(0,56%)]`, hero `hidden` + `lg:flex` | PASS |
| AC-006 / UI-06 | Narrow viewport: one column, hero hidden/compact, wordmark on the form, full-width controls | `Create.test.jsx:276-283` — hero `hidden`, form contains `Reserva`/`Salas`, primary/login `w-full` | PASS |
| AC-007 / UI-07 | `Reservation/Index` wraps the stub heading in `AppLayout` | `Index.test.jsx:14` heading `Reservas`; `Index.test.jsx:23-26` heading lives in a `min-h-screen` ancestor with `overflow-x-hidden` | PASS |
| AC-008 / UI-08 | `AppLayout` uses `min-h-screen`, horizontal padding, `overflow-x-hidden`, max width on `main` | `AppLayout.test.jsx:23-26` — `min-h-screen`, `overflow-x-hidden`, `main` `max-w-` and `px-` | PASS |
| AC-009 / UI-09 | Tailwind CSS v4 via `@tailwindcss/vite` and `@import 'tailwindcss'` | `package.json:11,20` `tailwindcss` / `@tailwindcss/vite` `^4.0.0`; `vite.config.js` `tailwindcss()`; `resources/css/app.css:1` `@import 'tailwindcss'` | PASS |
| AC-010 / UI-10 | Style changes stay Tailwind utilities | `Create.jsx`, `AppLayout.jsx`, `Index.jsx` only add/change utility classes (`flex`, `min-h-dvh`, `items-center`, `justify-center-safe`, `overflow-x-hidden`). No `@theme` tokens and no second design system | PASS |

Edge cases:

| Edge case | Evidence | Result |
| --------- | -------- | ------ |
| Short viewport / card taller than window | `Create.test.jsx:306` `justify-center-safe` | PASS |
| No horizontal scrollbar on register / reservations | `Create.test.jsx:280,296` and `Index.test.jsx:25` `overflow-x-hidden` | PASS |
| `lg` breakpoint two-column vs one-column | `Create.test.jsx:274-277` | PASS |
| `AppLayout` without `title` still renders children | `AppLayout.test.jsx:48-49` | PASS |

## Test Results

Local author gates (harness re-runs the required gates independently; this is not the official harness verdict):

- `npm test`: 8 files, **35 passed** (0 failed). Prior baseline was 29 Vitest cases. Added 6 (Create +2, AppLayout +3, Index +1). No deletions or skips.
- `php artisan test --testsuite=Unit`: **2 passed** (4 assertions).
- `php artisan test --testsuite=Feature`: **22 passed** (136 assertions). Existing `/register` HTTP cases unchanged.
- `vendor/bin/pint --test`: passed.
- `npm run build`: passed (Tailwind v4 Vite pipeline compiled). `public/build` is gitignored.

No new PHP Feature cases (layout-only; Inertia HTML did not break).

## Required Gates

| Gate | Command | Local run | Official harness |
| ---- | ------- | --------- | ---------------- |
| frontend | `npm test` | 35 passed | pending harness rerun |
| unit | `php artisan test --testsuite=Unit` | 2 passed | pending harness rerun |
| integration | `php artisan test --testsuite=Feature` | 22 passed | pending harness rerun |
| lint | `vendor/bin/pint --test` | passed | pending harness rerun |
| build | `npm run build` | passed | pending harness rerun |

Playwright is not a gate.

## Review Result

Pending independent harness review. Execute did not spawn a Verifier sub-agent (harness REVIEW owns that step).

## Final Status

**READY FOR HARNESS GATES.** T1–T3 done. No blockers. No extra production files beyond the plan. `validation.md` is the only new spec artifact. Extra dependency: `npm ci` and `composer install` in the worktree so gates could run; those trees stay untracked.

<!-- harness-checks:start -->
## Harness deterministic checks

- ✅ `npm test` — exit=0 (required)
- ✅ `php artisan test --testsuite=Unit` — exit=0 (required)
- ✅ `php artisan test --testsuite=Feature` — exit=0 (required)
- ✅ `vendor/bin/pint --test` — exit=0 (required)
- ✅ `npm run build` — exit=0 (required)
- ✅ `php artisan test` — exit=0 (required)
- ✅ `vendor/bin/pint --test` — exit=0 (required)
- ✅ `npm run build` — exit=0 (required)
<!-- harness-checks:end -->
