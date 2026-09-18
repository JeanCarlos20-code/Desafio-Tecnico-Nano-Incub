# Validation

## Acceptance Criteria

AC-001. Authenticated `GET /rooms/create` renders Inertia `Room/Create` with copy `Nova sala` / `Preencha as informações da sala de reunião.`
Evidence: `tests/Feature/Room/RoomStoreHttpTest.php:29` `->assertInertia(fn (Assert $page) => $page->component('Room/Create'))`; `resources/js/Pages/Room/Create.test.jsx:61` `expect(screen.getByRole('heading', { name: 'Nova sala' })).toBeInTheDocument()`; `resources/js/Pages/Room/Create.test.jsx:62` `expect(screen.getByText('Preencha as informações da sala de reunião.')).toBeInTheDocument()`.

AC-002. Create shows only required `Nome` and `Capacidade` and does not display or submit Status/`is_active`.
Evidence: `resources/js/Pages/Room/Create.test.jsx:74` `expect(screen.queryByLabelText('Situação')).not.toBeInTheDocument()`; `resources/js/Pages/Room/Create.test.jsx:77` `expect(useForm).toHaveBeenCalledWith({ name: '', capacity: '' })`; `resources/js/Pages/Room/Create.test.jsx:89` `expect(Object.keys(form.data).sort()).toEqual(['capacity', 'name'])`.

AC-003. Valid `POST /rooms` persists an active ADR-004 room and redirects to `/rooms` with `Sala criada com sucesso.`
Evidence: `tests/Feature/Room/RoomStoreHttpTest.php:44` `->assertRedirect(route('rooms.index'))`; `tests/Feature/Room/RoomStoreHttpTest.php:45` `->assertSessionHas('success', 'Sala criada com sucesso.')`; `tests/Feature/Room/RoomStoreHttpTest.php:55` `$this->assertTrue($room->is_active)`.

AC-004. Posted create `is_active` is ignored; stored value stays true.
Evidence: `tests/Unit/Room/CreateRoomTest.php:17` `$this->assertTrue($created->isActive)` after `execute('Sala Azul', 12, false)`; `tests/Feature/Room/RoomStoreHttpTest.php:103` `'is_active' => 1` after posting `'is_active' => false`.

AC-005. Invalid create name/capacity return the Portuguese field messages and persist nothing.
Evidence: `tests/Unit/Room/StoreRoomRequestTest.php:17` `['Informe o nome da sala.']`; `tests/Unit/Room/StoreRoomRequestTest.php:26` `['Informe a capacidade da sala.']`; `tests/Unit/Room/StoreRoomRequestTest.php:36` `['A capacidade deve ser um número inteiro.']`; `tests/Unit/Room/StoreRoomRequestTest.php:56` `['A capacidade deve ser de pelo menos 1 pessoa.']`; `tests/Feature/Room/RoomStoreHttpTest.php:85` `$this->assertDatabaseCount('rooms', 0)`.

AC-006. Processing `Salvar` shows `Salvando...` and disables `Salvar` and `Cancelar`.
Evidence: `resources/js/Pages/Room/Create.test.jsx:141` `expect(submit).toBeDisabled()`; `resources/js/Pages/Room/Create.test.jsx:142` `expect(cancel).toBeDisabled()`; `resources/js/Pages/Room/Create.test.jsx:147` `expect(form.post).not.toHaveBeenCalled()`.

AC-007. Form `Cancelar` goes to `/rooms` without mutating.
Evidence: `resources/js/Pages/Room/Create.test.jsx:155` `expect(screen.getByRole('link', { name: 'Cancelar' })).toHaveAttribute('href', '/rooms')`; `resources/js/Pages/Room/Create.test.jsx:156` `expect(form.post).not.toHaveBeenCalled()`.

AC-008. Successful `GET /rooms/{room}/edit` reuses the shared form, prefills the room, shows read-only status, and passes `has_registered_meetings` false.
Evidence: `tests/Feature/Room/RoomUpdateHttpTest.php:35-40` `component('Room/Edit')` plus `where('has_registered_meetings', false)`; `resources/js/Pages/Room/Edit.test.jsx:90` `expect(screen.queryByRole('combobox')).not.toBeInTheDocument()`; `resources/js/Pages/Room/Edit.test.jsx:93` `expect(useForm).toHaveBeenCalledWith({ name: 'Sala Azul', capacity: 10 })`.

AC-009. Active edit shows `Desativar sala` and opens confirmation before any PUT.
Evidence: `resources/js/Pages/Room/Edit.test.jsx:102` `expect(form.put).not.toHaveBeenCalled()`; `resources/js/Pages/Room/Edit.test.jsx:103` `expect(screen.getByRole('dialog')).toBeInTheDocument()`.

AC-010. With no registered meetings the radio `Desativar sala sem reunião` stays selected and keep/cancel questions stay hidden.
Evidence: `resources/js/Pages/Room/Edit.test.jsx:113` `expect(screen.getByLabelText('Desativar sala sem reunião')).toBeChecked()`; `resources/js/Pages/Room/Edit.test.jsx:114-115` keep/cancel labels are absent.

AC-011. Confirmed deactivation persists `is_active` false and redirects with `Sala atualizada com sucesso.`
Evidence: `resources/js/Pages/Room/Edit.test.jsx:139-142` transform payload `{ name, capacity, is_active: false }`; `tests/Feature/Room/RoomUpdateHttpTest.php:60` `->assertSessionHas('success', 'Sala atualizada com sucesso.')`; `tests/Feature/Room/RoomUpdateHttpTest.php:66` `'is_active' => 0`.

AC-012. `PUT` with name and capacity and no `is_active` keeps the stored status.
Evidence: `tests/Unit/Room/UpdateRoomTest.php:51` `$this->assertFalse($updated->isActive)` after omitted status; `tests/Feature/Room/RoomUpdateHttpTest.php:118` `'is_active' => 0` after PUT without `is_active`.

AC-013. Guest create/store redirects to `/login`.
Evidence: existing `tests/Feature/Room/RoomGuestHttpTest.php:30` `->assertRedirect(route('login'))` for `GET /rooms/create` and `POST /rooms`.

AC-014. Saving an inactive status does not open a reservation-write path.
Evidence: no reservations table or write code was added; `resources/js/Pages/Room/Edit.test.jsx:143-144` confirmed payload has neither `keep_meetings` nor `reservations`.

AC-015. Create/edit layout follows the screen card, required asterisks, field errors, and bottom-right actions, with Status-select deactivation replaced by the button and radios.
Evidence: `resources/js/Pages/Room/Create.test.jsx:67-73` `aria-required` and asterisks; `resources/js/Pages/Room/Create.test.jsx:128-132` `aria-invalid` / `aria-describedby`; `resources/js/Pages/Room/Components/RoomForm.jsx` centered card and `sm:justify-end` actions.

AC-016. `rooms.is_active` remains boolean with MySQL default true.
Evidence: `tests/Feature/Room/RoomSchemaTest.php:62` default is `true`/`1`/`'1'`; `tests/Feature/Room/RoomSchemaTest.php:73` `$this->assertTrue((bool) DB::table('rooms')->where('id', $id)->value('is_active'))` after insert omitting `is_active`.

AC-017 (human repair). Rooms UI does not display the room id in the list or the form. The identifier stays in persistence, Inertia props, React keys, and `/rooms/{room}` routes.
Evidence: `resources/js/Pages/Room/Index.test.jsx:78` `expect(screen.queryByRole('columnheader', { name: 'ID' })).not.toBeInTheDocument()`; `resources/js/Pages/Room/Index.test.jsx:79` `expect(screen.queryByText('id-1')).not.toBeInTheDocument()`; `resources/js/Pages/Room/Edit.test.jsx:92` `expect(screen.queryByText('id-1')).not.toBeInTheDocument()`; `resources/js/Pages/Room/Index.test.jsx:91-93` edit link still uses `href="/rooms/id-1/edit"`.

AC-018 (human repair). Rooms list table stays responsive after the ID column was removed: leftover width goes to `Nome`; `Capacidade`, `Status`, `Criada em`, and `Ações` stay compact (`w-0` + `whitespace-nowrap`); `Ações` is right-aligned; the desktop table scrolls horizontally inside `overflow-x-auto`; mobile keeps stacked cards (`md:hidden`).
Evidence: `resources/js/Pages/Room/Index.test.jsx:109-122` `table` `w-full` + `table-auto`; scroll parent `overflow-x-auto`; surface `min-w-0` + `md:block`; `Nome` `min-w-0`; `Ações` `w-0` / `whitespace-nowrap` / `text-right`; other compact headers `whitespace-nowrap`; mobile list `md:hidden`; ID header still absent.

AC-019 (human repair). Inertia success flashes such as `Sala atualizada com sucesso.` and `Sala criada com sucesso.` appear as one shared transient corner toast (fixed bottom-right), with a bluish `bg-blue-600` surface, high-contrast `text-white font-semibold` copy, an accessible close control named `Fechar`, `aria-live="polite"`, and auto-dismiss after 4 seconds. Laravel/Inertia `flash.success` remains the data source.
Evidence: `resources/js/Components/FlashToast.test.jsx:21` `toHaveTextContent('Sala atualizada com sucesso.')`; `resources/js/Components/FlashToast.test.jsx:23-29` `fixed` / `bottom-4` / not `top-4` / `right-4` / `bg-blue-600` / `text-white` / `font-semibold`; `resources/js/Components/FlashToast.test.jsx:47-48` toast gone after `FLASH_TOAST_DURATION_MS`; `resources/js/Components/FlashToast.test.jsx:54` `getByRole('button', { name: 'Fechar' })` click dismisses immediately; `resources/js/Layouts/AppLayout.test.jsx:170-177` create flash bottom-right + Fechar; `resources/js/Layouts/AppLayout.test.jsx:187-192` update flash; `resources/js/Layouts/AppLayout.test.jsx:217` AppLayout auto-dismiss; `tests/Feature/Room/RoomUpdateHttpTest.php:60` session flash still `Sala atualizada com sucesso.`.

## Test Results

This repair round applies human feedback: move the flash popup to the bottom corner, use a bluish site-matching color with prominent letters, and add an X so the user can close it faster. Latest review `review/review-04.md` verdict `APPROVED`. No structured blockers or high findings. No required red checks in the packet. Medium SMELL-001 is out of this repair scope.

Root cause: `FlashToast` was `fixed top-4 right-4` with a white/`text-slate-800` surface and no close control.

Fix: keep the shared Inertia-fed toast in `AppLayout`. Position is `fixed bottom-4 right-4`. Surface is `bg-blue-600` with `text-white font-semibold` (same primary as nav current and primary buttons). Close button `aria-label="Fechar"` dismisses immediately. Auto-dismiss remains 4000 ms. Message text is React children (no HTML sink). Session flash strings are unchanged.

Local checks (not the harness final gate): `npx vitest run resources/js/Components/FlashToast.test.jsx resources/js/Layouts/AppLayout.test.jsx` — 11 passed; `npx eslint` on the toast files — exit 0; `npm run test:coverage` — 93 passed, thresholds met, `FlashToast.jsx` 100%.

E2E: not applicable. No Playwright project exists; see `harness.tests_not_applicable.e2e`. Browser tools were not available in this worker; toast show, close X, and auto-dismiss were verified by Vitest with fake timers.

Additional files (real dependency of the shared flash presentation, not listed in the original 0009 plan):
- `resources/js/Components/FlashToast.jsx` — shared bottom-right bluish toast with Fechar.
- `resources/js/Components/FlashToast.test.jsx` — position, palette, close, auto-dismiss.
- `resources/js/Layouts/AppLayout.jsx` — consumes Inertia flash through FlashToast (unchanged this round except via FlashToast).
- `resources/js/Layouts/AppLayout.test.jsx` — create/update flash presentation + close + auto-dismiss.

Previous additional files from earlier repairs remain:
- `resources/js/Pages/Room/Index.jsx` — rooms list table layout; room id stays off the UI.
- `resources/js/Pages/Room/Index.test.jsx` — list columns + responsive layout assertions.
- `docs/screens/screen-rooms-list.md`
- `harness/stack.yml`

## Required Gates

Harness will re-run: unit (`php artisan test --testsuite=Unit --coverage --min=80`), frontend (`npm run test:coverage`), integration (`php artisan test --testsuite=Feature`), lint (`vendor/bin/pint --test`), frontend_lint (`npm run lint`), php_build (`composer run build`), frontend_build (`npm run build`), and `python3 -m pytest tests -q`. Do not treat this report as the final green gate.

## Review Result

Latest review `review/review-04.md` verdict `APPROVED`. No blockers. No high. Medium SMELL-001 is out of this repair scope. Human feedback this round: popup at the bottom corner, bluish color matching the site, prominent letters, and an X to close faster. Previous human fixes kept: rooms UI does not display the room id; rooms table stays responsive; toast remains a transient corner popup fed by Inertia flash.

## Final Status

REPAIR complete for task 0009. Inertia flashes render as a bottom-right bluish toast with prominent white text, a Fechar X, and 4-second auto-dismiss. Room list and form still do not show the room id. Rooms table layout is unchanged in this round. No commit from this worker.

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
- ✅ `python3 -m pytest tests -q` — exit=0 (required)
- ✅ `python3 -m compileall -q src` — exit=0 (required)
- ✅ `PYTHONPATH=src python3 -c "import project_harness"` — exit=0 (required)
<!-- harness-checks:end -->
