# Task Context

## Relevant Documentation

- `docs/architecture.md` — React → Service → Inertia → route → thin controller → FormRequest → use case → Domain → repository. Application has no HTTP/Eloquent. Controllers adapt only.
- `docs/tree.md` — Reservation lives under `app/Modules/Reservation/{Domain,Application,Infra}`. Inertia pages under `resources/js/Pages/Reservation/`.
- `docs/adr/005-modulo-reservation-contrato-minimo-ocupacao-e-cancelamento.md` — Reservation owns occupancy (RF07–RF18, RNF09). Explicitly excluded “edição de reserva”. Schema already has `title`, `responsible`, `starts_at`, `ends_at`, `room_id`, `participants`, `cancelled_at`. Active = `cancelled_at` null. Do not rewrite this decision; ADR-009 supersedes only the no-edit slice.
- `docs/adr/001-delimitar-escopo-aos-requisitos-rf-e-rnf.md` — RF catalog has no reservation edit. RF05 is room edit. If touched, add an extra note only; do not rewrite the RF list.
- `docs/adr/008-identidade-autoincremento-rooms-e-reservations.md` — `reservations.id` is bigint. Route param is an integer string.
- `docs/screens/screen-reservation-create.md` — Create field labels, required asterisks, AppLayout, Portuguese messages. Edit reuses this visual language; occupancy widgets become read-only.
- `docs/screens/screen-reservations-list.md` — Actions column today is Cancelar only. Note that says the challenge has no edit action must be updated. Edit link sits next to Cancelar for active rows.
- `docs/screens/screen-room-form.md` — Capacity-reduction copy still says “Altere essa reunião” = cancel then recreate. This task does not let that screen shrink `participants`.
- `docs/test/unit.md` — Use cases + FormRequest + React with fakes/mocks. No real MySQL, no real HTTP.
- `docs/test/integration.md` — Laravel request → middleware → FormRequest on the route → controller → use case → Eloquent → MySQL 8. One wiring case per validation family.
- `docs/test/e2e.md` — Browser + React + Laravel + MySQL for selected full flows. `package.json` has no Playwright project. `harness/stack.yml` lists `npx playwright test` but it is not runnable. Do not bootstrap Playwright.
- `README.md` § “O que ficou de fora” still says ADR-001 cut reservation edit. Must say partial metadata edit exists; occupancy stays locked; capacity reduction still cannot lower `participants` here.

## Relevant Components

- `app` / Reservation module: `CreateReservation`, `CancelReservation`, `ListReservations`, `StoreReservationRequest`, create/index/cancel controllers, `ReservationRepository`, Eloquent + Fake, Inertia `Reservation/Create` and `Reservation/Index`, `resources/js/Services/reservations.js`.
- Room edit is the HTTP/Inertia twin: `GET /rooms/{room}/edit`, `PUT /rooms/{room}`, `EditRoomController` + `FindRoom`, `UpdateRoom` + `UpdateRoomRequest`, `Pages/Room/Edit.jsx`, `Services/rooms.update`.
- Auth group in `routes/web.php` already wraps reservation routes. Guest Feature data provider must gain GET edit and PUT.
- No schema work. Columns already exist. No overlap/duration/lock on update (those fields do not change).

## Relevant Code

- `Reservation` entity: readonly `id`, `roomId`, `responsible`, `title`, `startsAt`, `endsAt`, `participants`, `cancelledAt`, timestamps, `roomName`.
- `ReservationRepository`: `create`, `findById`, `markCanceled`, list/overlap/capacity helpers. No metadata update yet. `findById` does not eager-load `room` (`roomName` is `''` unless `with('room')`).
- `CancelReservation`: missing → `ReservationNotFound`; already cancelled → no-op. Edit/update treat cancelled as not found (404), not idempotent success.
- `CancelReservationController`: `ReservationNotFound` → `abort(404)`. Repeat that mapping.
- `StoreReservationRequest`: `responsible`/`title` `required|string|max:255`, trim in `prepareForValidation`. Reuse those rules. Occupation keys on update are `prohibited` (chosen over silent ignore).
- `StoreReservationController`: `validated()` only; maps application errors to fields; success `redirect()->route('reservations.index')`.
- `IndexReservationController::toListItem`: list `starts_at` is display (`d/m/Y H:i` or `H:i`). Edit props must send machine values (`Y-m-d`, `H:i`) for disabled date/time inputs, plus `room_name` and `participants`.
- `RowActions` in `Index.jsx`: active → Cancelar only; inactive → em dash. List query already hides cancelled. Add `Editar` → `/reservations/{id}/edit` beside Cancelar.
- `reservations.js`: `store` POST, `cancel` PATCH. Add `update(form, id)` → `form.put(`/reservations/${id}`)`.
- `ReservationGuestHttpTest` data provider: add GET `/reservations/{id}/edit` and PUT `/reservations/{id}`.
- `phpunit.xml`: Application coverage ≥80% includes `app/Modules/Reservation/Application`. New use case is in that path.
- Gates: `php artisan test --testsuite=Unit --coverage --min=80`, `php artisan test --testsuite=Feature`, `npm run test:coverage`, `vendor/bin/pint --test`, `npm run lint`, `composer run build`, `npm run build`.

## Existing Constraints

- Occupancy fields (`starts_at`, `ends_at`, `room_id`, `participants`) and `cancelled_at` stay immutable on this update. Changing them would reopen RF13–RF18 and RNF09.
- Only active reservations (`cancelled_at` null). Cancelled or missing → 404. No edit UI for cancelled (they are already absent from the list).
- No overlap, duration, past-start, capacity, inactive-room, or `FOR UPDATE` on update.
- No migration / schema change.
- Do not edit ADR-005’s decision body. New ADR-009 (MADR, Portuguese) supersedes only “não editar reserva”. Occupancy remains immutable. ADR-001: extra note only.
- Capacity-reduction sentence on Room/Edit is unchanged. This screen does not lower `participants`. README and `screen-room-form.md` must say that explicitly.
- Domain stays pure PHP. No Illuminate in Application.
- E2E not in this plan.

## Important Decisions

- Persist `title` and `responsible` only via `ReservationRepository::updateTitleAndResponsible`. Use case `UpdateReservation` trims, loads by id, throws `ReservationNotFound` if missing or cancelled, writes those two columns, returns the entity.
- GET edit: thin `EditReservationController` uses `findById` (with room), `abort(404)` if null or cancelled, Inertia `Reservation/Edit`.
- PUT: `UpdateReservationRequest` requires `title` and `responsible` (same messages as store). `starts_at`, `ends_at`, `room_id`, `participants`, `cancelled_at` are `prohibited`. Controller passes only `validated()` into `UpdateReservation`. Extra unknown keys stay dropped by `validated()`.
- Reject (not ignore) occupation keys so a crafted payload cannot pretend to move the meeting.
- No `FindReservation` use case. Cancelled-as-404 is shared: use case for PUT, controller guard for GET (same predicate). Unit coverage of the rule sits on `UpdateReservation`; GET 404 is Feature.
- No transaction/lock on update.
- Past or in-progress actives may change title/responsible. Time rules are create-only.
- Flash: `Reserva atualizada com sucesso.` Redirect `reservations.index`.
- ADR-005 status line gets a pointer to ADR-009 (same style as the ADR-008 identity note). Decision text stays.
- New screen `docs/screens/screen-reservation-edit.md`. Extend the list screen for `Editar`.
- Confirmed lessons: none.
