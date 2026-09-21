# Task Context

## Relevant Documentation

- `docs/adr/005-modulo-reservation-contrato-minimo-ocupacao-e-cancelamento.md` — `cancelled_at` null = active; set = cancelled (RF11/RF12). Occupancy ignores cancelled rows. Whether the list shows cancelled rows is an ADR-005 gap; pick the simplest path. Do not add completed/recurrence/calendar.
- `docs/screens/screen-reservations-list.md` — still says cancelled rows disappear, only `Ativa` badges, and `Editar`/`Cancelar` only on active rows. Replace the hide-on-cancel list rule with a Status filter. Keep Período radios (`Todos` / `Hoje` / `Amanhã` / `1 semana`), `Data inicial` / `Data final` (`starts_on` / `ends_on`), room filter, pagination `page`+`limit` (ADR-010), chronological order, and the cancel dialog. The Filters table still documents a stale `date` param; T5 must align it with period/range while adding Status. Do not add a “list past meetings” radio.
- `docs/screens/screen-rooms-list.md` — Status select Todas/Ativas/Inativas. Rooms **code and screen default is `all` (`Todas`)**, not `active`. Copy the control/enum/query pattern, not the rooms default.
- `docs/architecture.md` / `docs/tree.md` — thin controller → FormRequest → use case → repository interface → Eloquent. React page + `Services/reservations.js` `visitIndex`. Domain stays pure PHP.
- `docs/test/unit.md` — use case + FormRequest (no HTTP/DB); React Index with Inertia mocked. Exhaustive `status` enum/omit/invalid matrix at unit.
- `docs/test/integration.md` — GET `/reservations` through middleware + FormRequest + controller + MySQL 8. One 422 for invalid `status`. Persistence/occupancy stay real. Do not repeat the full validation matrix. Do not re-prove the period/range window matrix except where it must compose with status.
- `docs/test/e2e.md` — browser + React + Laravel + MySQL for selected full flows. No Playwright project or dependency exists; `harness/stack.yml` lists `npx playwright test` but it is not runnable. Do not bootstrap Playwright. Filter UI + HTTP listing are covered at unit + integration.
- User request (keep original): reunião passada already stays on `period=all`; cancelled rows vanish because `listPage()` has a fixed `whereNull('cancelled_at')`. Requested filter `all` / `active` / `cancelled`; no `completed`. “cancelar uma reserva não faz mais ela desaparecer quando o filtro for all.”
- Later human revision: only add cancelled filtering; keep existing period/range; no past-meetings radio.
- This-round human revision (supersedes omitted → `all`): “é basicamente o mesmo em rooms la em ativo e inativo, e por falar em rooms, em ambos o default é ativo.” Reservation Status default is `active` (`Ativas`). Do not change the rooms list in this task (rooms still default to `all`).
- Repair human: “permanece nessa” + “deixa o default do periodo em hoje.” Keep the status filter. Omitted `period` is `today`. `Limpar filtros` visits `period=today`.

## Relevant Components

- Module `Reservation`: `IndexReservationRequest`, `IndexReservationController`, `ListReservations`, `ReservationRepository::listPage()`, `EloquentReservationRepository`.
- Inertia page `resources/js/Pages/Reservation/Index.jsx` + Vitest `Index.test.jsx`. `visitIndex` already forwards the query object. Existing `PERIODS`: `all`/`today`/`tomorrow`/`week`. Range: `starts_on` / `ends_on`. There is no `yesterday` preset.
- Pattern to copy: Room list Status `<select id="status">` Todas/Ativas/Inativas, Portuguese `status.in` message, repository predicates. **Do not copy rooms omit-when-`all`.** Rooms omit `status` because their default is `all`. Reservations omit `status` when it equals **their** default (`active`).
- Occupancy (do not change): `hasActiveOverlap`, `markCanceled`, room-lifecycle cancel methods, `ReservationCancelHttpTest` (cancelled interval is reusable).
- Gates (`harness/stack.yml`): PHP Unit `--coverage --min=80` (Application only), Feature, `npm run test:coverage`, Pint, ESLint, `composer run build`, `npm run build`. Do not run Playwright.

## Relevant Code

- `EloquentReservationRepository::listPage()` always `whereNull('cancelled_at')`. Period/range still filter `starts_at` (`period=all` → unbounded; `today`/`tomorrow`/`week` or complete `starts_on`+`ends_on` → half-open window). `hasAny()` also ignores cancelled, so a catalogue of only cancelled would show “Nenhuma reserva cadastrada”.
- `IndexReservationController::toListItem()` hardcodes `'status' => 'active', 'status_label' => 'Ativa'`. Domain `Reservation` already has `cancelledAt`. `RowActions` already hides Editar/Cancelar when `status !== 'active'` and `StatusBadge` already uses slate when not active (Index test uses a fake `inactive`/`Inativa` row).
- `IndexReservationRequest` has `room_id`, `period` (`in:all,today,tomorrow,week`), `starts_on`, `ends_on`, `page`, `limit`. Default period in the controller is `today`.
- `ListReservations` forwards window bounds only. `FakeReservationRepository::listPage()` always drops cancelled rows — that is why `ListReservationsTest::test_it_clamps_page_to_one_and_excludes_cancelled_at_rows` passes today. After this task, default `status=active` still excludes cancelled; `hasAny` for a cancelled-only catalogue becomes true.
- Feature contracts: `ReservationIndexHttpTest` `test_index_filters_by_room_with_the_resolved_window_and_excludes_canceled_rows` (`period=today`, omitted status) stays a default-active case; echo `filters.status` = `active`. `test_index_hides_rows_canceled_by_standalone_deactivate_and_delete` stays true for omitted/`active`; add `status=all` so those titles appear as `Cancelada`. Keep period-preset and range Feature tests. `Index.test.jsx` currently asserts no `Cancelada` badge — replace with cancelled-row + Status select cases; keep Período radios + date range cases.
- `ReservationCancelHttpTest::test_cancel_sets_cancelled_at_keeps_the_row_and_frees_the_interval` already proves RF12. Leave occupancy queries with `whereNull('cancelled_at')`.
- Rooms (do not edit): `IndexRoomController` `$status = $request->validated('status') ?? 'all'`; `Room/Index.jsx` `statusQuery` omits when `all`.

## Existing Constraints

- Soft-delete cancelled from the list is the current screen/test contract. Default `active` keeps that for `/reservations` with omitted status. History is via `status=all` or `status=cancelled`. Update tests after the behavior change; do not delete occupancy/cancel dialog/period/range coverage.
- Do not add `completed` / `ends_at < now`. Past active rows stay `Ativa`.
- Do not add a past-meetings radio, a `yesterday` period, or a default that hides `ends_at < now`. Time filters stay `period` + `starts_on`/`ends_on`.
- Do not change create/update/cancel routes, overlap lock, identity, period/range URL contract, or the rooms list default.
- `hasAny` must count any reservation row (including cancelled) so Status=`Ativas` on a cancelled-only catalogue is filtered-empty, not catalogue-empty.
- PHP Application coverage >= 80%. React coverage >= 80%. FormRequest Portuguese message for invalid status: `Informe um status válido.` (same as rooms).
- No new ADR. Fill the ADR-005 listing gap in the reservations screen doc only.

## Important Decisions

- Query `status`: `all` | `active` | `cancelled`. Omitted → **`active`** (this-round human: default Ativas). Rooms remain `all`; do not retcon rooms.
- Repository (not the use case) applies: `active` → `whereNull('cancelled_at')`; `cancelled` → `whereNotNull('cancelled_at')`; `all` → no `cancelled_at` predicate. `listPage` / `ListReservations::execute` default param is `'active'` so callers that omit status keep today’s hide-cancelled query. Period/range/room compose with status.
- Controller maps `cancelledAt !== null` → `cancelled` / `Cancelada`; else `active` / `Ativa`. Echo `filters.status` (default `active`).
- React: Status `<select id="status">` in the existing filter card (Todas / Ativas / Canceladas). Keep Período radiogroup and date range. Omit `status` from visits when `active`; include `status=all` and `status=cancelled`. Reset `page` to 1 on status change; keep room/period/range/`limit`. `Limpar filtros` visits `period=today` and omits `status`. Cancelled rows keep `—` actions.
- After cancel with default omitted/`active`, the row leaves the default list (same as today). With `status=all` it stays as `Cancelada`. Occupancy still frees the slot.
- Past meetings: `period=all` already lists them; a custom past window is `Data inicial` + `Data final`. No extra control.
- Rewrite the screen doc hide-on-cancel paragraphs and the stale `date` filter row. Update Feature/Vitest cases named above; do not add Playwright.
