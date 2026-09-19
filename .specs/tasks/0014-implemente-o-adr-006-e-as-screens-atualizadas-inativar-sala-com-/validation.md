# Validation

## Acceptance Criteria

Authorized repair of ARCH-001 and TEST-001 from review-01. AC-004 / AC-006: a 422 deactivation decision must reopen `Desativar sala?` from `errors.future_active_count` without an Inertia page-prop rerender.

- `Edit` keeps `futureActiveCount` in local state, initialized from the page prop.
- `onError` parses `errors.future_active_count`, promotes a positive integer into that state, then sets `reopenDialog`.
- `RoomForm` receives the state count, so `reopenDialog && futureActiveCount > 0` can open after a 422 that started with a zero page prop.
- The dead `visitOptions(onDialogRequired)` parameter was removed because `submit` never passed it; count promotion lives in `onError`.

## Test Results

| Check | Local result |
| --- | --- |
| `npx vitest run resources/js/Pages/Room/Edit.test.jsx` | 6 passed (6), including 422 reopen without `rerender` of `future_active_count` |
| `npx eslint` on the two touched files | pending / run with complete-phase gates |

`harness.tests` items were not added or removed. TEST-001 rewrote the existing unit case so it fails unless `onError` consumes the error count. No integration or e2e tests were added (422 HTTP + count remains in Feature; e2e N/A).

Harness re-runs the official phase checks after complete-phase.

## Required Gates

Listed in `tasks.md` `harness.gates`. Repair did not declare them green. The harness runner is the source of truth.

## Review Result

review-01 verdict was `REJECTED` (ARCH-001, TEST-001). This repair addresses those two highs only. SMELL-001 (medium) was not opened as a new front; the unused callback disappeared because ARCH-001 replaced that path with local count state.

## Final Status

Repair of ARCH-001 and TEST-001 is ready for harness repair checks. No product commit was made.

### Extra files (dependency)

None. Only `resources/js/Pages/Room/Edit.jsx` and `resources/js/Pages/Room/Edit.test.jsx` changed for the authorized findings.

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
