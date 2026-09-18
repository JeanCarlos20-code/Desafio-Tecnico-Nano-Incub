# Task Context

## Relevant Documentation

- `docs/screens/screen-room-form.md` — create/edit room form: `GET /rooms/create` + `POST /rooms`; `GET /rooms/{room}/edit` + `PUT /rooms/{room}`; create omits Status; backend assigns active status. Human revision overrides edit Status-select deactivation: a **Desativar sala** button starts the rule; confirmation sets `is_active` false; keep/cancel-meeting questions appear only when meetings exist; with no meetings the radio `Desativar sala sem reunião` stays selected. The other-database reservation transaction stays ignored.
- `docs/screens/screen-rooms-list.md` — `Nova sala` already goes to `/rooms/create`; shared `AppLayout`; flash live region.
- `docs/adr/004-modulo-rooms-ciclo-de-vida-minimo.md` — persist `id`, `name`, `capacity`, `is_active`, timestamps, `deleted_at`. `is_active` is boolean RF03/RF17. Unique name is not required.
- `docs/adr/001-delimitar-escopo-aos-requisitos-rf-e-rnf.md` — RF04 create, RF05 edit, RF02 auth, RNF08/RNF14 server validation. Reservation RF07–RF18 later.
- `docs/adr/003-react-e-php-no-mesmo-projeto-laravel.md` — Inertia is the UI contract; no public REST.
- `docs/architecture.md` / `docs/tree.md` — thin invokable controllers; Form Request → use case → Domain port; React under `resources/js/Pages/Room/` with feature Components and `Services/rooms.js`.
- `docs/test/unit.md` — use cases + FormRequest (off-route) + React pages/forms; no real HTTP/DB.
- `docs/test/integration.md` — Laravel request + middleware + FormRequest on route + controller + use case + Eloquent + MySQL 8; do not repeat the unit matrix.
- `docs/test/e2e.md` — browser + React + Laravel + MySQL for selected full flows. No Playwright project exists; do not bootstrap one here.
- User text (keep original): “esse room form deve ter a rota de criação de room, haverá um momento q o sistema ficará pendente de uma transaction com outro banco que não foi criado ainda, esse você pode ignorar por enquanto”.
- Human revision (keep original): “o status do is_active quando tiver fazendo a sala, ele é boolean true como default, n sei se foi definido isso na query mas deve ser assim, no update, vai ter um botão para desativar a sala e ae sim entra a regra, se confirmado o is_active fica false, e tbm as perguntas de desativar outras reuniões só vai aparecer se tiver reunião registrada, se n tiver, só mantenha o radiobutton do desativar sala sem reunião ativo”.

## Relevant Components

- `app` monolith: Laravel + Inertia 2 + React + Tailwind + Eloquent/MySQL 8.
- Room module from task 0007: Domain `Room` + `RoomRepository`; Application `CreateRoom`, `FindRoom`, `UpdateRoom`; Infra invokable controllers and `StoreRoomRequest` / `UpdateRoomRequest`.
- Authenticated routes in `routes/web.php`: `rooms.create` `GET /rooms/create`, `rooms.store` `POST /rooms`, `rooms.edit`, `rooms.update` `PUT /rooms/{room}`.
- Frontend: `Pages/Room/Create.jsx` (exports shared `RoomForm`), `Pages/Room/Edit.jsx`, `Services/rooms.js` (`store` → `form.post('/rooms')`, `update` → `form.put`), `Layouts/AppLayout.jsx`.
- Tests: `tests/Unit/Room/CreateRoomTest.php`, `StoreRoomRequestTest.php`, `UpdateRoomRequestTest.php`, `UpdateRoomTest.php`; `tests/Feature/Room/RoomStoreHttpTest.php`, `RoomUpdateHttpTest.php`, `RoomGuestHttpTest.php`, `RoomSchemaTest.php`, `tests/Feature/Database/RoomsMigrationTest.php`; Vitest `Create.test.jsx`, `Edit.test.jsx`, `rooms.test.js`.
- Gates (`harness/stack.yml`): PHPUnit Unit+Feature, `npm run test:coverage`, Pint, ESLint, `composer run build`, `npm run build`. `npx playwright test` is catalogued but not runnable.

## Relevant Code

- Migration `database/migrations/2026_09_18_120000_create_rooms_table.php` already has `$table->boolean('is_active')->default(true)`. Factory also defaults `is_active` true. Eloquent model casts `is_active` to boolean.
- `CreateRoom::execute($name, $capacity, ?bool $isActive = null)` persists `$isActive ?? true`. `StoreRoomController` forwards client `is_active` when present. `StoreRoomRequest` includes `is_active` => `sometimes|boolean`. That still lets `POST /rooms` create an inactive room.
- `Create.jsx` `useForm({ name, capacity, is_active: true })` and `RoomForm` always render a `Situação` select. Copy is `Informe os dados da sala de reunião.` Submit label is `Cadastrar sala`.
- `Edit.jsx` reuses that form, prefills `room.is_active`, submits `PUT` via `update(form, room.id)`. No deactivate button, no confirmation radios.
- `UpdateRoomRequest` treats `is_active` as `sometimes`. `UpdateRoomController` defaults missing `is_active` to `true` — a name/capacity save on an inactive room would reactivate it.
- `UpdateRoom::execute` always requires a bool status and writes it through the repository.
- `CreateRoomController` renders `Room/Create` with no extra props. `EditRoomController` passes `{ id, name, capacity, is_active }` or 404. No `has_registered_meetings` prop; there is no reservations table.
- Guest room routes already redirect to `/login`. Index `Nova sala` already points at `/rooms/create`. Flash strings already match (`Sala criada com sucesso.`, `Sala atualizada com sucesso.`).
- User register screen is the UX pattern for asterisks, `aria-required`, `onError` focus, processing label, and `Não foi possível … Tente novamente.`
- `.local/image/screen-room-form.png` is not in this worktree; follow the markdown visual table.

## Existing Constraints

- Do not add reservation persistence, a second database, or keep/cancel meeting writes. User said to ignore that pending transaction.
- Do not invent unique room names, max capacity, extra columns, public REST, roles, or Playwright.
- Domain/Application must stay free of `Illuminate`. Controllers stay thin. Writes use Form Request `validated()`, never `$request->all()`.
- Existing 0007 tests are contracts: update them only where this spec changes create-status, form fields, and deactivation UX.
- PHP coverage gate is Application only (`>= 80%`). React coverage `>= 80%` via Vitest.

## Important Decisions

- Keep the MySQL `is_active` boolean default `true` (already in the migration). Also force `CreateRoom` to persist `true` and drop `is_active` from `StoreRoomRequest` so a posted status is ignored.
- Create UI: Nome + Capacidade only; no Status/`is_active` control in the payload.
- Edit UI (human revision): no Status select as the deactivation control. Show current status as read-only `Ativa`/`Inativa`. While the room is active, show button `Desativar sala`. That button opens the deactivation confirmation; only after confirm does `PUT` send `is_active` false.
- Confirmation radios: if `has_registered_meetings` is false, show radio `Desativar sala sem reunião` selected and do not show keep/cancel-meeting questions. If true, show `Manter reuniões programadas` / `Cancelar reuniões programadas` and hide the no-meeting radio. Confirming still only persists `is_active` false in this task (no reservation writes).
- `EditRoomController` passes `has_registered_meetings: false` (no meetings store). Vitest may pass `true` to cover the meeting-question branch.
- `Salvar` on edit submits only `name` and `capacity`. Omitted `is_active` must keep the stored status. Fix `UpdateRoomController` default-true. `UpdateRoom` treats a null status as “keep current”.
- Inactive rooms: hide `Desativar sala`. Show `Ativar sala`; confirming it `PUT`s `is_active` true with no meeting radios (RF05 activate without the deactivation rule).
- Align capacity messages with the screen: integer → `A capacidade deve ser um número inteiro.`; min → `A capacidade deve ser de pelo menos 1 pessoa.`; missing still `Informe a capacidade da sala.`
- Extract `Pages/Room/Components/RoomForm.jsx` with `mode` (`create` | `edit`). Reuse `AppLayout` and `Services/rooms.js`; no new routes.
- E2E not applicable: no Playwright runner. Human may refuse that reason.
