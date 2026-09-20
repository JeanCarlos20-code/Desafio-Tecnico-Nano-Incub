---
harness:
  commits:
    - "feat(reservation): lock create date and time pickers to now"
    - "test(reservation): cover create past date and time lock"
    - "chore(docs): document create date and time min bounds"
    - "chore(specs): record past-booking lock context"
    - "chore(specs): record past-booking lock progress"
    - "chore(specs): record past-booking lock reviews"
    - "chore(specs): record past-booking lock spec"
    - "chore(specs): record past-booking lock tasks"
    - "chore(specs): record past-booking lock checks"
    - "chore(specs): record past-booking lock validation"
  tests:
    unit:
      - "minScheduleBounds reports today YYYY-MM-DD and current HH:MM in the given timezone"
      - "minScheduleBounds returns a start-time min only when the selected date is today"
      - "minScheduleBounds treats starts_at before now as past and equal-to-now as not past"
      - "Reservation/Create date input min is today and start-time min is current HH:MM while the selected date is today"
      - "Reservation/Create omits start-time min when the selected date is after today"
      - "Reservation/Create submit with a past date or a today start before now shows A data não pode estar no passado. and does not call form.post"
      - "Reservation/Create submit with today start equal to now still posts the existing starts_at transform"
      - "Reservation/Create submit with end time not after start time shows O término deve ser posterior ao início. and does not call form.post"
      - "minScheduleBounds treats end not after start as inverted and end after start as valid"
      - "CreateReservation rejects starts_at before Clock::now() with A data não pode estar no passado. and persists nothing"
      - "CreateReservation accepts starts_at equal to Clock::now()"
    integration:
      - "Authenticated POST /reservations with starts_at before Clock::now() returns starts_at = A data não pode estar no passado. and inserts no reservation row"
    e2e: []
  tests_not_applicable:
    e2e: "No Playwright project, config, or dependency exists in package.json. stack.yml lists npx playwright test but it is not runnable. Picker min and submit lock are React unit (mocked clock). Server RF18 is already covered by CreateReservation unit tests and ReservationStoreHttpTest on MySQL 8. docs/test/e2e.md forbids repeating those matrices in the browser. Do not bootstrap Playwright in this task."
  gates:
    - id: unit
      command: "php artisan test --testsuite=Unit --coverage --min=80"
      required: true
    - id: frontend
      command: "npm run test:coverage"
      required: true
    - id: integration
      command: "php artisan test --testsuite=Feature"
      required: true
    - id: lint
      command: "vendor/bin/pint --test"
      required: true
    - id: frontend_lint
      command: "npm run lint"
      required: true
    - id: php_build
      command: "composer run build"
      required: true
    - id: frontend_build
      command: "npm run build"
      required: true
---

# Implementation Plan

## Summary

Lock the create reservation calendar and start-time picker to now-or-later, and refuse a past start in React before POST. Keep RF18 on `CreateReservation` (`starts_at < Clock::now()` → `StartsInPast`). Do not change FormRequest, list filters, or edit occupancy.

**Design (inline, no `design.md`):**

- New helper `resources/js/Pages/Reservation/minScheduleBounds.js` using `Intl.DateTimeFormat` with the Inertia `timezone` string:
  - `calendarDateInTimeZone(now, timeZone)` → `YYYY-MM-DD`
  - `clockTimeInTimeZone(now, timeZone)` → `HH:MM` (24-hour, zero-padded)
  - `minStartTime(selectedDate, now, timeZone)` → `HH:MM` when `selectedDate === today`, otherwise `''`
  - `isStartInPast(date, startTime, now, timeZone)` → `true` when date is before today, or today and `${date} ${HH:MM}:00` is before `now`; empty date/time → `false` (required path unchanged); equal to now → `false`
  - export `PAST_START_MESSAGE = 'A data não pode estar no passado.'`
- `Create.jsx`: accept `timezone = 'UTC'`; compute bounds from `new Date()`; date `min={minDate}`; start-time `min={minStart || undefined}`; on submit, if `isStartInPast` then show `PAST_START_MESSAGE` on date/start_time, focus that field, return without `transform`/`store`.
- Do not edit `CreateReservation`, `StoreReservationRequest`, `Index.jsx`, or `Edit.jsx`.
- Keep existing PHP unit + `ReservationStoreHttpTest` past-start cases; do not add a second Feature RF18 test.
- Screen doc: date `min` today; start-time `min` on today; React submit lock; live error string.

## Affected Components

- `app` — `resources/js/Pages/Reservation/Create.jsx`, new helper + Vitest, `Create.test.jsx`, `docs/screens/screen-reservation-create.md`. Existing PHP RF18 tests stay.
- Do not change list filters, edit, schema, timezone config, or Playwright.

## Tasks

Execute T1 → T3 as in **Task Breakdown**.

## Planned Tests

### Unit

See `harness.tests.unit`. Helper tests freeze `Date` and pass `UTC` (and one non-UTC case if cheap). Create page tests mock Inertia; freeze `Date` to `2026-09-21T08:00:00.000Z` so existing `2026-09-21 10:00` submit stays valid. PHP CreateReservation past/equal cases already exist — do not rewrite them.

### Integration

See `harness.tests.integration`. Keep `ReservationStoreHttpTest` past-start assertion (`2026-09-21 07:00` with clock `08:00`). Do not add another Feature test for the same POST path. GET create already sends `timezone`; no new Inertia assertion required unless Execute touches the controller (it must not).

### E2E

Not applicable — no Playwright project. See `harness.tests_not_applicable.e2e`.

## Required Gates

After Execute, before review, run every `harness.gates` command from the worktree root. Unit coverage remains Application-only (≥80%). Frontend coverage via `npm run test:coverage` (≥80%). Do not run `npx playwright test`.

## Definition of Done

- Create date picker cannot offer days before today; start-time picker on today cannot offer times before now.
- React submit of a past start shows the live Portuguese error and does not POST.
- A valid now-or-later start still posts the existing transform.
- Server RF18 still rejects a crafted past `starts_at` and still accepts equal to now.
- List and edit calendars unchanged.
- Create screen doc records the lock.
- Pint, ESLint, PHP build, and Vite build pass.
- No product commit in PLAN.

---

## Test Coverage Matrix

> Generated from codebase, project guidelines, and spec. Guidelines found: `docs/test/unit.md`, `docs/test/integration.md`, `docs/test/e2e.md`, `phpunit.xml` (Application coverage ≥80%), `package.json` (`npm run test:coverage`).

| Code Layer | Required Test Type | Coverage Expectation | Location Pattern | Run Command |
| --- | --- | --- | --- | --- |
| minScheduleBounds helper | unit | today / HH:MM / start-time min only on today / past vs equal-now / end not after start | `resources/js/Pages/Reservation/minScheduleBounds.test.js` | `npm run test:coverage` |
| Reservation/Create | unit | date min, start-time min, omit min on future date, block past submit, still post equal-now, block end not after start | `resources/js/Pages/Reservation/Create.test.jsx` | `npm run test:coverage` |
| CreateReservation RF18 | unit | Existing past reject + equal-now accept; do not duplicate | `tests/Unit/Reservation/CreateReservationTest.php` | `php artisan test --testsuite=Unit --coverage --min=80` |
| Store create HTTP RF18 | integration | Existing past POST session error + no row; do not duplicate | `tests/Feature/Reservation/ReservationStoreHttpTest.php` | `php artisan test --testsuite=Feature` |
| Create screen doc | none | Copy only | `docs/screens/screen-reservation-create.md` | build gate only |

## Gate Check Commands

> Generated from `composer.json`, `package.json`, `phpunit.xml`.

| Gate Level | When to Use | Command |
| --- | --- | --- |
| Quick | After PHP unit-only tasks | `php artisan test --testsuite=Unit --coverage --min=80` |
| Frontend | After React tasks | `npm run test:coverage` |
| Full | After Execute, before review | `php artisan test --testsuite=Unit --coverage --min=80` && `php artisan test --testsuite=Feature` && `npm run test:coverage` |
| Build | Phase end / lint | `vendor/bin/pint --test` && `npm run lint` && `composer run build` && `npm run build` |

Cwd: worktree root.

---

## Execution Plan

Phases run sequentially. Tasks inside a phase run in order.

### Phase 1: React lock and screen doc

```
T1 -> T2 -> T3
```

---

## Task Breakdown

### T1: Add timezone-aware create schedule bounds helper

**What**: Add `minScheduleBounds.js` with today, current `HH:MM`, start-time min only on today, and `isStartInPast` using the same `< now` rule as `CreateReservation`. Cover the three helper items in `harness.tests.unit`.
**Where**: `resources/js/Pages/Reservation/minScheduleBounds.js`
**Depends on**: None
**Reuses**: App timezone string already passed as Inertia `timezone`; RF18 operator `<`
**Requirement**: PAST-01, PAST-02, PAST-03, PAST-04, PAST-07

**Done when**:

- [x] Helper exports `calendarDateInTimeZone`, `clockTimeInTimeZone`, `minStartTime`, `isStartInPast`, and `PAST_START_MESSAGE`
- [x] Today + current `HH:MM` resolve in the given timezone; start-time min is empty for a future date
- [x] Date before today, or today + time before now, is past; equal to now is not past
- [x] Gate check passes: `npm run test:coverage`

**Tests**: unit
**Gate**: frontend

---

### T2: Wire Create date and start-time min plus submit lock

**What**: Consume the helper in `Create.jsx` (`timezone` prop default `UTC`). Set date/start-time `min`. Block past submit with `PAST_START_MESSAGE` and no `form.post`. Keep existing happy-path transform. Cover the four Create page items in `harness.tests.unit`. Freeze `Date` to `2026-09-21T08:00:00.000Z` in `Create.test.jsx` so the current `2026-09-21 10:00` post case stays valid. Do not change Index, Edit, or PHP production files.
**Where**: `resources/js/Pages/Reservation/Create.jsx`
**Depends on**: T1
**Reuses**: `combineDateTime`; existing Field error rendering; `store(form, …)`
**Requirement**: PAST-01, PAST-02, PAST-03, PAST-04, PAST-05, PAST-08

**Done when**:

- [x] Date input `min` is today; start-time `min` is current `HH:MM` only when date is today
- [x] Past date or today+past time shows `A data não pode estar no passado.` and does not call `form.post`
- [x] Today + start equal to now still posts `starts_at` / `ends_at` as today
- [x] Index list date filters and Edit occupancy inputs are untouched
- [x] Gate check passes: `npm run test:coverage`

**Tests**: unit
**Gate**: frontend

---

### T3: Document create date and time min bounds

**What**: Update `screen-reservation-create.md` Date and Start time sections: `min` today; start-time `min` on today; React submit lock; keep Laravel as authority; use the live error string `A data não pode estar no passado.`
**Where**: `docs/screens/screen-reservation-create.md`
**Depends on**: T2
**Reuses**: Existing Date / Start time / Reservation in the past sections
**Requirement**: PAST-01, PAST-02, PAST-04, PAST-06

**Done when**:

- [x] Screen doc states date `min` = today in `app.timezone` / page timezone
- [x] Screen doc states start-time `min` on today and the React no-POST lock
- [x] Screen doc keeps server RF18 and the live Portuguese error

**Tests**: none
**Gate**: build

---

## Phase Execution Map

```
Phase 1:  T1 -> T2 -> T3
```

Execution is strictly sequential.

---

## Task Granularity Check

| Task | Scope | Status |
| --- | --- | --- |
| T1: minScheduleBounds helper | 1 module | Granular |
| T2: Create.jsx lock | 1 page | Granular |
| T3: create screen doc | 1 file | Granular |

## Diagram-Definition Cross-Check

| Task | Depends On (task body) | Diagram Shows | Status |
| --- | --- | --- | --- |
| T1 | None | (root) | Match |
| T2 | T1 | T1 -> T2 | Match |
| T3 | T2 | T2 -> T3 | Match |

## Test Co-location Validation

| Task | Code Layer Created/Modified | Matrix Requires | Task Says | Status |
| --- | --- | --- | --- | --- |
| T1 | minScheduleBounds helper | unit | unit | OK |
| T2 | Reservation/Create | unit | unit | OK |
| T3 | Create screen doc | none | none | OK |
