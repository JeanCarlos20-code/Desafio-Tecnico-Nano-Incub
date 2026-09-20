# Validation

## Acceptance Criteria

| AC / Done-when / edge | `file:line` + assertion | Spec-defined outcome | Covered? |
| --- | --- | --- | --- |
| AC-001 date `min` = today `YYYY-MM-DD` in page timezone | `Create.test.jsx:209` - `expect(screen.getByLabelText(/^Data/)).toHaveAttribute('min', '2026-09-21')` | `2026-09-21` at frozen `2026-09-21T08:00:00.000Z` UTC | Yes |
| AC-002 start-time `min` = current `HH:MM` while date is today | `Create.test.jsx:210` - `expect(screen.getByLabelText(/Horário de início/)).toHaveAttribute('min', '08:00')` | `08:00` | Yes |
| AC-003 omit start-time `min` on a future date | `Create.test.jsx:217` - `expect(screen.getByLabelText(/Horário de início/)).not.toHaveAttribute('min')` | no `min` / empty | Yes |
| AC-004 past date blocks POST and shows date-focused error | `Create.test.jsx:236-238` - `expect(screen.getAllByText('A data não pode estar no passado.')).not.toHaveLength(0)` and `expect(pastDate.form.post).not.toHaveBeenCalled()` | new live string + no `form.post` | Yes |
| AC-004 today + start before now blocks POST | `Create.test.jsx:256-257` - same message and `expect(pastTime.form.post).not.toHaveBeenCalled()` | message + no `form.post` | Yes |
| AC-005 equal-to-now still posts existing transform | `Create.test.jsx:320-328` - `expect(form.post).toHaveBeenCalledTimes(1)` and `starts_at: '2026-09-21 08:00:00'` | POST `/reservations` with combined datetimes | Yes |
| AC-006 server past POST error + no row | `ReservationStoreHttpTest.php:148` - `assertSessionHasErrors(['starts_at' => 'A data não pode estar no passado.'])`; `CreateReservationTest.php:90,93` - same message and `$reservations->created` is `[]` | date-focused string, no insert | Yes |
| AC-007 equal to `Clock::now()` accepted | `CreateReservationTest.php:61` - `assertNull($min->cancelledAt)` after execute with `$now` | accept equal start | Yes (existing; not rewritten) |
| AC-008 list/edit unchanged | Index.jsx and Edit.jsx were not edited | keep current list filters and disabled occupancy | Yes |
| AC-009 end not after start blocks POST | `Create.test.jsx:279-281` - `expect(screen.getByText('O término deve ser posterior ao início.')).toBeInTheDocument()` and `expect(inverted.form.post).not.toHaveBeenCalled()` | live FormRequest string + no `form.post` | Yes |
| AC-009 equal start/end also blocked | `Create.test.jsx:299-301` - same message and `expect(equal.form.post).not.toHaveBeenCalled()` | `after:starts_at` is strict | Yes |
| Helper today / HH:MM in timezone | `minScheduleBounds.test.js:14-17` - UTC `2026-09-21`/`08:00`; Sao Paulo `05:00` | timezone-local date and clock | Yes |
| Helper start-time min only on today | `minScheduleBounds.test.js:25-27` - today `08:00`, tomorrow `''`, empty `''` | empty when not today | Yes |
| Helper past vs equal-now | `minScheduleBounds.test.js:34-37` - before today / `07:00` true; `08:00` false | `< now` past; equal not past | Yes |
| Helper inverted end/start | `minScheduleBounds.test.js:48-51` - `09:00` and `10:00` true; `10:30` false | `end <= start` inverted | Yes |
| Edge: leftover seconds (`08:00:30` vs `08:00:00`) | `minScheduleBounds.test.js:44` - `isStartInPast(..., nowWithSeconds)` is `true` | `starts_at < now` | Yes |
| Edge: empty date/time does not invent required | `minScheduleBounds.test.js:40-41` and `:53-55` - empty date or time is `false` | existing required path | Yes |
| Edge: tomorrow `00:00` allowed by past rule | `minScheduleBounds.test.js:39` - `isStartInPast('2026-09-22', '00:00', ...)` is `false` | not past | Yes |

Human repair asked to replace `O horário inicial não pode estar no passado.` with date-focused copy, and to block an inverted end vs start. The create form has one calendar day plus start/end times, so the inverted case is `end_time` not after `start_time`. List `Data inicial` / `Data final` filters stay unrestricted (AC-008).

Extra files beyond the original T1–T3 list, required by that feedback:

- `app/Modules/Reservation/Application/Errors/StartsInPast.php` — keep React and server on the same live string.
- `tests/Unit/Reservation/CreateReservationTest.php` and `tests/Feature/Reservation/ReservationStoreHttpTest.php` — those tests encoded the superseded copy; updated because the approved message changed.
- `spec.md`, `tasks.md`, `context.md`, `docs/screens/screen-reservation-create.md` — record the new copy and AC-009.

## Test Results

Repair worker (worktree):

- `npm run test -- resources/js/Pages/Reservation/minScheduleBounds.test.js resources/js/Pages/Reservation/Create.test.jsx`: 15 passed.
- `php artisan test --filter='CreateReservationTest|StoreReservationRequestTest'`: 10 passed (46 assertions).
- `npx eslint` on the touched Reservation JS files: passed.
- `vendor/bin/pint --test` on the touched PHP files: passed.
- Existing RF18 comparison (`starts_at < now`, equal accepted) was not changed.
- Existing FormRequest `ends_at` `after:starts_at` was not changed; no second Feature matrix was added.
- E2E: not applicable (no Playwright project).

Final gate color is owned by the harness runner after `complete-phase`.

## Required Gates

Listed in `tasks.md` `harness.gates`. Repair did not declare them green. Harness re-runs every required gate after this phase.

## Review Result

Latest review `review-01.md` verdict was `APPROVED`. No structured blockers or high findings. No required check was red. Scope was the human feedback: date-focused past copy plus a React lock when end is not after start.

## Final Status

Repair updated the live past-start copy to `A data não pode estar no passado.` on React and `StartsInPast`, and added a React submit lock (plus end-time `min`) when end is not after start using `O término deve ser posterior ao início.`. Product tests for those outcomes passed locally. No product commit was created.

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
