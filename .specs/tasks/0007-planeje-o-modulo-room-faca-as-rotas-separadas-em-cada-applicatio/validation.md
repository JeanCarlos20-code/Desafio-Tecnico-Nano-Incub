# Validation

## Acceptance Criteria

Repair scoped to human commit-gate feedback on the admin sidebar height. Round-2 review was APPROVED with no blockers/high. ARCH-001 and other mediums were not reopened. Room CRUD/domain was not changed.

- Left admin column fills the viewport on both `/reservations` (short stub) and `/rooms` (taller table): shell is `h-screen min-h-screen` flex; `aside` is `h-full`; right `main` scrolls (`overflow-y-auto`). Covered by `resources/js/Layouts/AppLayout.test.jsx` (`keeps the left nav at full viewport height independent of children height`).
- Desktop / landscape tablet keeps the expanded navy sidebar; smaller viewports keep the existing accessible drawer (`Abrir navegação` / overlay). No extra product chrome.
- `ReservaSalas` wordmark, `Reservas` + `Salas` with `aria-current`, account logout, and flash live region remain. Nav links are `w-full` so the active item is not a content-sized pill. Covered by the same layout tests plus the existing `marks Salas with aria-current...` case.
- Reservation stub still wraps in the viewport shell (`min-h-screen` + `overflow-x-hidden`). Covered by `resources/js/Pages/Reservation/Index.test.jsx`.

Existing ROOM-01…ROOM-07 / AC-001…AC-015 coverage from Execute is unchanged.

## Test Results

Worker checks (not the harness final gate):

- Vitest `resources/js/Layouts/AppLayout.test.jsx`: 5 passed (1 new full-height case; existing logout/`aria-current` cases kept).
- Vitest `resources/js/Pages/Reservation/Index.test.jsx`: 2 passed.
- Vitest Room pages (`Index`/`Create`/`Edit`): 11 passed.
- ESLint on `AppLayout.jsx` and `AppLayout.test.jsx`: passed.

No tests skipped, weakened, or deleted.

## Required Gates

| Gate | Command | Worker result |
| ---- | ------- | ------------- |
| unit | `php artisan test --testsuite=Unit --coverage --min=80` | not re-run this round (untouched Application PHP) |
| integration | `php artisan test --testsuite=Feature` | not re-run this round (untouched HTTP/PHP) |
| frontend | `npm run test:coverage` | targeted Vitest passed; full coverage left to harness |
| lint | `vendor/bin/pint --test` | not re-run (no PHP change) |
| frontend_lint | `npm run lint` | passed on touched AppLayout files |
| php_build | `composer run build` | left to harness |
| frontend_build | `npm run build` | left to harness |

Harness re-runs required gates on `complete-phase`. This table is not the final green certificate.

## Review Result

Round 2 was APPROVED. This repair addresses human commit-gate feedback only: the left nav now fills the viewport instead of stretching with page content. No structured review findings were in scope.

## Final Status

REPAIR ready for task `0007`. `harness task complete-phase 0007 --phase repair` is the next command. No product commit from this worker.

### Extra files beyond `tasks.md`

No extra product files. Changes stayed on T5 paths:

- `resources/js/Layouts/AppLayout.jsx` — viewport-height left nav, scrolling main, full-width nav links.
- `resources/js/Layouts/AppLayout.test.jsx` — full-height shell independent of children height.

No ARCH-001 / Room CRUD / domain changes. No reservation FK, no extra room columns, no Playwright.

<!-- harness-checks:start -->
## Harness deterministic checks

- ✅ `php artisan test --testsuite=Unit --coverage --min=80` — exit=0 (required)
- ✅ `php artisan test --testsuite=Feature` — exit=0 (required)
- ✅ `npm run test:coverage` — exit=0 (required)
- ✅ `vendor/bin/pint --test` — exit=0 (required)
- ✅ `npm run lint` — exit=0 (required)
- ✅ `composer run build` — exit=0 (required)
- ✅ `npm run build` — exit=0 (required)
- ✅ `php artisan test` — exit=0 (required)
- ✅ `vendor/bin/pint --test` — exit=0 (required)
- ✅ `composer run build && npm run build` — exit=0 (required)
- ✅ `python3 -m pytest tests -q` — exit=0 (required)
- ✅ `python3 -m compileall -q src` — exit=0 (required)
- ✅ `PYTHONPATH=src python3 -c "import project_harness"` — exit=0 (required)
<!-- harness-checks:end -->
