# Task Context

## Relevant Documentation

- `docs/adr/001-delimitar-escopo-aos-requisitos-rf-e-rnf.md` — RF05 (edit room), RF16 (participants must not exceed room capacity). Reservation **edit** is an ADR-001 cut; cancel + recreate is the only way to change participants after create.
- `docs/adr/004-modulo-rooms-ciclo-de-vida-minimo.md` — Rooms owns `capacity`. Changing capacity already noted as affecting reservation rules; no write-side guard exists today.
- `docs/adr/005-modulo-reservation-contrato-minimo-ocupacao-e-cancelamento.md` — RF16 is enforced on **create** (`CreateReservation` vs current `OccupancyRoom.capacity`). Changing room capacity is called out as affecting RF16; the reverse path (edit room) is unspecified.
- `docs/adr/006-serializar-ciclo-da-sala-com-reservas-novas-e-existentes.md` — `UpdateRoom` already `transaction->run` + `lockById`. Future active = `cancelled_at` null and `starts_at > now`. In-progress / past / already canceled are ignored for the deactivate dialog. Reuse that definition for “reunião marcada”.
- `docs/screens/screen-room-form.md` — Edit saves `name`, `capacity`, `is_active`. Capacity field already shows Laravel errors (`id="capacity-error"`). No copy today for a capacity-vs-meetings conflict.
- `docs/architecture.md` — Rule lives in Application; controller only maps the error to HTTP; React is UX.
- `docs/test/unit.md`, `docs/test/integration.md`, `docs/test/e2e.md` — classification used for `harness.tests`. No Playwright in `package.json`.
- `docs/tree.md` — Room Application / Errors / HTTP; Reservation repository port + Eloquent.

## Relevant Components

- `app` / Room: `UpdateRoom`, `UpdateRoomController`, `UpdateRoomRequest` (input contract unchanged), `DeactivationDecisionRequired` (pattern to copy).
- `app` / Reservation: `ReservationRepository`, `EloquentReservationRepository`, `FakeReservationRepository`. Field is `participants` (not attendees).
- React: `resources/js/Pages/Room/Edit.jsx` already focuses `#capacity` when `errors.capacity` is present. `RoomForm.jsx` already renders `form.errors.capacity`. No new widget required if the backend puts the message on `capacity`.
- Tests: `tests/Unit/Room/UpdateRoomTest.php`, `tests/Feature/Room/RoomUpdateHttpTest.php`, `resources/js/Pages/Room/Edit.test.jsx`.

## Relevant Code

- `UpdateRoom::execute` locks the room, then optionally runs the ADR-006 deactivate path, then `$this->rooms->update(...)` with the new capacity. There is **no** read of reservation `participants`.
- `CreateReservation` rejects `participants > $room->capacity` with `CapacityExceeded` / `O número de participantes excede a capacidade da sala.`
- `ReservationRepository::countActiveFutureByRoom($roomId, $now)` counts future actives; it does **not** filter by `participants`.
- `UpdateRoomController` maps `DeactivationDecisionRequired` to `scheduled_meetings_action` + `future_active_count`. A new application error should map to `capacity` via `ValidationException::withMessages`.
- Routes: rooms update is `PUT /rooms/{room}`. Reservations have create + cancel only — no `reservations.update`.
- Clock in Feature room tests is frozen to `2026-09-21 12:00` in `config('app.timezone')`. Reuse that freeze so “future” means `starts_at > 2026-09-21 12:00`.

## Existing Constraints

- Domain stays pure PHP. Application must not import HTTP / Eloquent.
- Do not weaken ADR-006 deactivate/delete behavior.
- Do not add reservation edit (ADR-001 leftover).
- Do not add Playwright. Do not invent a max capacity.
- `UpdateRoomRequest` already validates `capacity` as `required|integer|min:1`. This task is **database-dependent business rule**, not a new FormRequest rule.
- Unit coverage gate is Application-only (`phpunit.xml`). Frontend coverage via `npm run test:coverage`.
- Confirmed tlc lessons: none.

## Important Decisions

- **No new ADR.** ADR-004/005 already say capacity changes affect RF16; ADR-006 already serializes `UpdateRoom` with the room lock. This task fills the missing write-side RF16 check. Recording a ninth ADR would document the same lock and the same future-active definition.
- **Block, do not auto-change meetings.** User: impede, then “altera essa reunião primeiro e depois volte”. No silent participant shrink, no auto-cancel on capacity drop (that would collide with the deactivate radios).
- **Which meetings count:** same as ADR-006 future actives (`cancelled_at` null, `starts_at > Clock::now()`) on **this** room whose `participants >` proposed capacity. Past, in-progress, canceled, other rooms, and meetings with `participants <=` new capacity do not block.
- **Equality is allowed** (`participants ==` new capacity). RF16 is “do not exceed”.
- **Increase / same capacity / name-or-status-only:** persist as today; do not run the conflict message.
- **Order inside the existing transaction, after lock:** (1) `RoomNotFound`; (2) capacity conflict → throw, write nothing; (3) existing deactivate path; (4) `rooms->update`. If both a too-small capacity and a missing deactivate action apply, the administrator sees the capacity message, not the dialog.
- **Message (Portuguese, on `capacity`):**
  - 1 meeting: `Não é possível reduzir a capacidade. Existe 1 reunião marcada com mais participantes do que a nova capacidade. Altere essa reunião primeiro e depois volte.`
  - n>1: `Não é possível reduzir a capacidade. Existem {n} reuniões marcadas com mais participantes do que a nova capacidade. Altere essas reuniões primeiro e depois volte.`
  - Do not list times or titles (keeps one validation string; “em tal tempo” in the request is the scenario, not a UI list).
- **Port:** `ReservationRepository::countActiveFutureExceedingCapacity(string $roomId, DateTimeImmutable $now, int $capacity): int` — Eloquent `participants > $capacity` plus the future-active predicates. Implement the same filter on `FakeReservationRepository`.
- **Error:** `App\Modules\Room\Application\Errors\CapacityReductionBlocked` with `public readonly int $conflictingCount` and the Portuguese `getMessage()` above (mirror `DeactivationDecisionRequired`).
- **Concurrency:** no new two-process test. The check runs after the existing `lockById`; `CreateReservation` already waits on that lock. Sequential Feature + `UpdateRoomTest` lock assertion is enough for this task.
- **React:** no production change unless the existing `errors.capacity` path is broken. Add a Vitest that the new sentence appears on the capacity field.
- **Docs:** one paragraph on `docs/screens/screen-room-form.md` under Capacity / edit save so the screen stays aligned. No ADR file.
- **“Altere essa reunião”** means cancel (and optionally create again with fewer participants). This task does not add reservation edit.
