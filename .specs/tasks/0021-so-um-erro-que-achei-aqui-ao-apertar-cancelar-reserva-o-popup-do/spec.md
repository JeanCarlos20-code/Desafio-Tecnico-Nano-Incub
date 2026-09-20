# Specification

## Context

The reservations list (`GET /reservations`) opens a confirmation dialog before cancel. The administrator reported that the dialog stays on screen after they use its buttons. The same leftover overlay happens on the rooms list (`GET /rooms`) after **Excluir sala**. Backend cancel/delete already persist and redirect to the index. This spec is only the dialog leaving the screen.

## Problem

After the administrator opens **Cancelar reserva?**, pressing **Voltar** or **Cancelar reserva** can leave the overlay visible. A leftover dialog blocks the list and the success flash even when the reservation was already canceled.

## Problem Statement

The cancel-reservation confirmation dialog can remain mounted after **Voltar** or after a successful **Cancelar reserva**. The administrator cannot see the updated list until the overlay is gone.

## Goal

- [x] **Voltar** (when not processing) hides the dialog and does not send `PATCH`.
- [x] A successful **Cancelar reserva** hides the dialog after Inertia `onSuccess`.
- [x] Failure and in-flight cancel keep the dialog as the screen spec already requires.
- [x] **Cancelar** on the rooms-list delete dialog (when not processing) hides the dialog and does not send `DELETE`.
- [x] A successful **Excluir sala** hides the delete dialog after Inertia `onSuccess`.

## User Stories

### P1: Dismiss the cancel dialog ⭐ MVP

**User Story**: As an administrator, I want the cancel-reservation popup to leave the screen when I press **Voltar** or when **Cancelar reserva** succeeds, so I can keep using the list.

**Why P1**: This is the reported defect; the list is unusable while the overlay stays.

**Acceptance Criteria** (each line is one EARS pattern):

1. WHEN the administrator presses `Voltar` on the open dialog and the cancel request is not processing THEN the system SHALL hide the dialog and SHALL NOT send `PATCH /reservations/{id}/cancel`.
2. WHEN the cancel `PATCH` succeeds THEN the system SHALL hide the dialog.
3. IF the cancel `PATCH` fails THEN the system SHALL keep the dialog visible and SHALL show `Não foi possível cancelar a reserva. Tente novamente.`
4. WHILE the cancel request is processing the system SHALL keep the dialog visible, disable `Voltar` and `Cancelar reserva`, and show `Cancelando...` on the destructive action.
5. WHEN the administrator presses `Cancelar` on the open rooms-list delete dialog and the delete request is not processing THEN the system SHALL hide the dialog and SHALL NOT send `DELETE /rooms/{id}`.
6. WHEN the delete `DELETE` succeeds THEN the system SHALL hide the dialog.

**Independent Test**: Open the dialog in `Reservation/Index` with Inertia mocked; click `Voltar` and assert no dialog / no PATCH; invoke `onSuccess` after confirm and assert no dialog; keep existing failure and processing cases. Same dismiss contract on `Room/Index` for `Cancelar` / successful `Excluir sala`.

---

## Acceptance Criteria

1. WHEN the administrator presses `Voltar` on the open dialog and the cancel request is not processing THEN the system SHALL hide the dialog and SHALL NOT send `PATCH /reservations/{id}/cancel`. (AC-001)
2. WHEN the cancel `PATCH` succeeds THEN the system SHALL hide the dialog. (AC-002)
3. IF the cancel `PATCH` fails THEN the system SHALL keep the dialog visible and SHALL show `Não foi possível cancelar a reserva. Tente novamente.` (AC-003)
4. WHILE the cancel request is processing the system SHALL keep the dialog visible, disable `Voltar` and `Cancelar reserva`, and show `Cancelando...` on the destructive action. (AC-004)
5. WHEN the administrator presses `Cancelar` on the open rooms-list delete dialog and the delete request is not processing THEN the system SHALL hide the dialog and SHALL NOT send `DELETE /rooms/{id}`. (AC-005)
6. WHEN the delete `DELETE` succeeds THEN the system SHALL hide the dialog. (AC-006)

## Edge Cases

- IF `form.processing` is true THEN the system SHALL ignore `Voltar` and `Escape` (controls disabled; no dismiss mid-request).
- WHEN `Escape` is pressed and the request is not processing THEN the system SHALL hide the dialog without PATCH (already implemented; do not regress).
- IF the list props no longer include the canceled row and Inertia preserved `pending` THEN the system SHALL still hide the dialog via the success callback (do not rely on remount).
- IF `form.processing` is true on the rooms-list delete dialog THEN the system SHALL ignore `Cancelar` and `Escape` (controls disabled; no dismiss mid-request).
- WHEN `Escape` is pressed on the rooms-list delete dialog and the request is not processing THEN the system SHALL hide the dialog without DELETE (already implemented; do not regress).
- IF the rooms list props no longer include the deleted row and Inertia preserved `pending` THEN the system SHALL still hide the dialog via the success callback (do not rely on remount).

## Out of Scope

| Feature | Reason |
| --- | --- |
| Backdrop / overlay click dismiss | Not in `screen-reservations-list.md` |
| Optimistic close on confirm | Would hide the dialog during processing and on failure |
| `preserveState: false` on cancel | Would remount the page and can reset filter draft state |
| Cancel use case, HTTP, MySQL, flash copy | Already implemented and tested |
| Playwright / E2E suite | Not in the project; dismiss is React-unit |
| Room deactivate dialog on Edit | Success redirects to `rooms.index` (different Inertia page), so the dialog unmounts with the form. `Cancelar` / `Escape` already hide it. |

## Assumptions & Open Questions

| Assumption / decision | Chosen default | Rationale | Confirmed? |
| --- | --- | --- | --- |
| Processing stays blocking | `Voltar` / `Escape` do not close while `form.processing` | Screen spec: disable controls and show `Cancelando...` | n |
| Confirm closes after success, not on click | Clear `pending` in Inertia `onSuccess` | Matches “after success, the dialog closes”; keeps failure visible | n |
| No backend change | Only page + Vitest | Controllers already cancel/delete and flash | n |
| Room delete uses the same dismiss rule | `onSuccess` clears `pending` on `Room/Index` | Human repair: the leftover overlay also happens when deleting rooms | n |
| Remaining implicit dimensions | N/A for this scope | No new auth, persistence, concurrency, or validation | n |

**Open questions:** none - all resolved or logged above.

## Considered Approaches

1. **`onSuccess` clears `pending`** — explicit dismiss after a good PATCH; `Voltar` keeps `closeCancel`; failure handlers unchanged.
2. **`preserveState: false` on the PATCH** — remounts the page so `pending` resets, but can drop local filter draft and does not name the dismiss rule.
3. **Optimistic close on button click** — matches “pressing the button closes it”, but hides processing and failure states the screen requires.

## Selected Approach

Approach 1. In `confirmCancel`, pass `onSuccess` that clears `pending` and `cancelError`. Keep `closeCancel` for `Voltar` / `Escape`. On the rooms list, `confirmDelete` passes the same `onSuccess` that clears `pending`. Keep `closeDelete` for `Cancelar` / `Escape`. Do not change service URL wiring or PHP.

## Requirement Traceability

| Requirement ID | Story | Phase | Status |
| --- | --- | --- | --- |
| CANCEL-01 | P1: Dismiss the cancel dialog | Execute | Implemented |
| CANCEL-02 | P1: Dismiss the cancel dialog | Execute | Implemented |
| CANCEL-03 | P1: Dismiss the cancel dialog | Execute | Implemented |
| CANCEL-04 | P1: Dismiss the cancel dialog | Execute | Implemented |
| ROOM-DEL-01 | P1: Dismiss the cancel dialog | Repair | Implemented |
| ROOM-DEL-02 | P1: Dismiss the cancel dialog | Repair | Implemented |

**Coverage:** 6 total, 6 mapped to tasks, 0 unmapped.

## Success Criteria

- [x] `Voltar` hides `role="dialog"` and does not call `form.patch`.
- [x] `onSuccess` after confirm hides `role="dialog"`.
- [x] Failure still shows the dialog + alert; processing still shows `Cancelando...`.
- [x] Rooms-list `Cancelar` hides `role="dialog"` and does not call `form.delete`.
- [x] `onSuccess` after room delete hides `role="dialog"`.
