# Task Context

## Relevant Documentation

- `docs/screens/screen-reservations-list.md` — cancel confirmation: `Voltar` / `Escape` close without mutation; `Cancelar reserva` sends `PATCH /reservations/{id}/cancel`; while processing, controls stay disabled (`Cancelando...`); after success the dialog closes and the row leaves the list; on failure the dialog stays with `Não foi possível cancelar a reserva. Tente novamente.`
- `docs/screens/screen-rooms-list.md` — delete confirmation: `Cancelar` / `Escape` close without mutation; `Excluir sala` sends `DELETE /rooms/{id}`; while processing, controls stay disabled (`Excluindo...`); after success the dialog closes.
- `docs/adr/005-modulo-reservation-contrato-minimo-ocupacao-e-cancelamento.md` — RF11 confirm-then-cancel; `cancelled_at` (no hard delete). No UI-state rule.
- `docs/test/unit.md` — React dialog open/close, button clicks, processing/error/success states are unit (Inertia mocked).
- `docs/test/integration.md` — Laravel request + MySQL only. No new HTTP/persistence work here.
- `docs/test/e2e.md` — full browser flows; do not retest React-only dismiss. No Playwright project in `package.json`.
- `docs/architecture.md` — pages own UI state; services isolate Inertia URLs (`cancel` in `resources/js/Services/reservations.js`).
- `docs/tree.md` — Reservation page at `resources/js/Pages/Reservation/`.
- Confirmed lessons: none.

## Relevant Components

- `app` — `resources/js/Pages/Reservation/Index.jsx` (dialog `pending` state) and `Index.test.jsx`.
- `app` — `resources/js/Pages/Room/Index.jsx` (delete dialog `pending` state) and `Index.test.jsx`.
- `cancel()` already forwards `form.patch` options. `destroy()` already forwards `form.delete` options. Controllers already persist and redirect to the matching index. Do not change PHP.

## Relevant Code

- Reservation `Index.jsx`: `pending` renders the overlay. `closeCancel()` clears `pending` unless `form.processing`. `confirmCancel()` now passes `onSuccess` that clears `pending`.
- Room `Index.jsx`: same overlay pattern for delete. `closeDelete()` already clears `pending` unless `form.processing`. `confirmDelete()` previously called `destroy(form, pending.id)` with no `onSuccess`, so the same-page DELETE redirect left the dialog mounted.
- Inertia v3 (`@inertiajs/react` ^3.7.1): same-page visits after PATCH can keep React state (`pending` stays, dialog stays) even when the list props refresh and the row is gone. `onSuccess` is the form-helper hook to clear that state (`https://inertiajs.com/docs/v3/the-basics/http-requests`).
- Existing Vitest: Escape + confirm PATCH; processing disables both buttons; failure keeps the dialog. Missing: `Voltar` click removes `role="dialog"`; success (`onSuccess`) removes `role="dialog"`.
- Backend already covered: `CancelReservationTest`, `ReservationCancelHttpTest` (PATCH, flash, idempotent repeat, 404). Do not restage those scenarios.

## Existing Constraints

- While `form.processing`, `Voltar` and `Cancelar reserva` stay disabled; do not dismiss mid-request (screen spec).
- Failure keeps the dialog and the alert; do not optimistic-close then reopen.
- Do not dismiss on backdrop click (not in the screen spec).
- Do not add Playwright or change cancel HTTP/use-case contracts.
- Keep `preserveState` as-is so filter draft (`pendingPeriod`, `draft`) is not remounted away.

## Important Decisions

- Scope: UI dismiss only. User report (kept original): *ao apertar "cancelar reserva" o popup ainda fica na tela; Voltar ou Cancelar reserva deve sair esse popup*. Repair feedback: the leftover overlay also happens when deleting rooms; scan the front and close those popups.
- Selected fix: pass `onSuccess` that clears `pending` (and `cancelError` on reservations). `Voltar` / rooms `Cancelar` already call the close helper; add unit assertions.
- Frontend scan: confirmation overlays exist on Reservation cancel, Room delete (same-page Inertia visit), and Room deactivate. Deactivate success redirects to `rooms.index` (different page), so that dialog remounts away. No other confirmation popups.
- Rejected: `preserveState: false` (extra remount, can reset filter draft); optimistic close on confirm (conflicts with processing + failure ACs).
- Classification: new tests are React unit only. Integration and E2E are not applicable for this delta.
