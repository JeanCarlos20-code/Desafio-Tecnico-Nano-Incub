# Validation

Repair round for human feedback: restore Brazilian date and time display (`dd/mm/yyyy`, 24-hour). No blockers or high findings in review-01. No red required checks.

## Acceptance Criteria

Previous Execute ACs AC-001–AC-012 remain in place (Participants `text-center`, rooms omitted status `active`, Passada/Ativa/Cancelada). This repair does not change those outcomes.

Human feedback (review-01): native date and time pickers must follow Brazil, not US.

| Outcome | Evidence |
| --- | --- |
| HTML locale defaults to `pt_BR` so `app.blade.php` emits `lang="pt-BR"` | `config/app.php` `locale` default `pt_BR`; blade already uses `app()->getLocale()` |
| List range date inputs use `lang="pt-BR"` | `resources/js/Pages/Reservation/Index.jsx` Data inicial/final. `Index.test.jsx` `toHaveAttribute('lang', 'pt-BR')` |
| Create date and time inputs use `lang="pt-BR"` | `Create.jsx` date / start_time / end_time. `Create.test.jsx` lang assertions |
| Edit date and time inputs use `lang="pt-BR"` | `Edit.jsx` date / start_time / end_time. `Edit.test.jsx` lang assertions |
| Schedule bounds formatter is `pt-BR` with 24-hour `hourCycle: 'h23'` | `minScheduleBounds.js`. Existing tests still expect `YYYY-MM-DD` / `HH:MM` ISO values |

### Extra files

Human feedback required date/time locale, which lives outside the original T1–T7 file list:

- `config/app.php` — default `APP_LOCALE` `pt_BR` so the Inertia document language is `pt-BR` (Firefox and other engines that honor page language stop forcing US `mm/dd/yyyy` / 12-hour time). Fallback stays `en` for missing Laravel translation keys.
- `resources/js/Pages/Reservation/Create.jsx`, `Edit.jsx` — same picker locale as the listing filters.
- `resources/js/Pages/Reservation/minScheduleBounds.js` — `Intl.DateTimeFormat` locale `pt-BR` instead of `en-US` (parts still assembled as ISO for `type="date"` / `type="time"` values).
- Matching Vitest assertions on those pages.

Query values remain `Y-m-d` / `HH:MM`. Native `type="date"` / `type="time"` stay; this repair only changes display locale.

## Test Results

Local Repair quick checks (worktree):

- Frontend (touched files): `npx vitest run` Index/Create/Edit/minScheduleBounds — 40 passed, exit 0
- E2E: not applicable

Harness re-runs the required gates on complete-phase. Do not treat the lines above as the official gate result.

## Required Gates

| Gate | Command | Local quick check |
| ---- | ------- | ----------------- |
| unit | `php artisan test --testsuite=Unit --coverage --min=80` | not re-run this repair (PHP product tests unchanged) |
| frontend | `npm run test:coverage` | related Vitest files exit 0 |
| integration | `php artisan test --testsuite=Feature` | not re-run this repair |
| lint | `vendor/bin/pint --test` | not re-run this repair |
| frontend_lint | `npm run lint` | not re-run this repair |
| php_build | `composer run build` | not re-run this repair |
| frontend_build | `npm run build` | not re-run this repair |

Official green/red is the harness complete-phase run, not this table.

## Review Result

review-01: APPROVED, no blockers/high, no red required checks. Human feedback asked to restore Brazilian date and time display. This repair addresses that feedback only.

## Final Status

Repair ready for harness deterministic checks. Date/time pickers use `pt-BR`. Spec ACs from Execute are unchanged. No product commit in this phase.

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
