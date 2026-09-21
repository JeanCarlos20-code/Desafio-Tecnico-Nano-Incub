# Reservations List Screen

## Overview

This screen allows an authenticated administrator to view, filter, create, edit metadata, and cancel meeting room reservations in the **ReservaSalas** system.

## Visual reference

The implementation must use the following image as a reference for composition, visual identity, hierarchy, spacing, navigation, filters, table structure, status badges, and action placement:

![Visual reference for the reservations list screen](.local/image/screen-reservations-list.png)

Reference file:

```text
.local/image/screen-reservations-list.png
```

> The image guides the appearance of the screen, but its exact dimensions do not need to be reproduced. The layout must adapt responsively to the available space while preserving the behaviors, required data, business rules, and accessibility requirements defined in this document.

> The reference image includes a calendar icon in each active reservation row. The implementation must omit this icon. The `Ações` column shows `Editar` (to `/reservations/{id}/edit`) beside `Cancelar` on active rows. Edit changes only title and responsible; occupancy stays locked.

- **Page route:** `GET /reservations`
- **Access:** authenticated administrators only
- **Create route:** `GET /reservations/create`
- **Edit route:** `GET /reservations/{reservation}/edit`
- **Cancel route:** `PATCH /reservations/{reservation}/cancel`
- **Expected technologies:** React, Inertia.js 2, Tailwind CSS, and Laravel

## Goal

Provide a central administrative view where the user can:

- view room reservations in chronological order (default **Ativas** hides cancelled rows);
- filter reservations by room;
- filter reservations by status (Todas / Ativas / Canceladas);
- filter reservations by period preset or by an inclusive start/end date range;
- open the new reservation form;
- open the partial edit form (`Editar`) for title and responsible only;
- cancel an active reservation after confirmation;
- verify that a canceled reservation stays listed when Status is Todas or Canceladas, and that occupancy still frees the interval.

Reservation data must come from the backend. Example rows from the visual reference must not be hardcoded in the React component.

## Shared administrative layout

The screen uses the authenticated administrative layout shared by the rooms section.

### Sidebar

The sidebar appears on the left with a dark navy background.

Elements:

1. `ReservaSalas` wordmark with a calendar icon.
2. Navigation item `Reservas` in the active state.
3. Navigation item `Salas`.

Navigation behavior:

| Item | Destination |
| --- | --- |
| `Reservas` | `/reservations` |
| `Salas` | `/rooms` |

The active `Reservas` item must be communicated visually and through `aria-current="page"`.

### Top bar

The top bar contains:

- the authenticated administrator's avatar or initials;
- the administrator's name;
- a dropdown indicator;
- an account menu containing at least the logout action.

User information must come from the authenticated session and must not be hardcoded.

## Main content header

The content header contains:

- title `Reservas`;
- supporting text `Acompanhe e gerencie as reservas de salas.`;
- primary button `Nova reserva` with a plus icon.

On desktop, filters appear below the title and the primary action stays aligned to the right. On smaller screens, these controls may stack vertically.

## New reservation action

Selecting `Nova reserva` navigates to:

```text
/reservations/create
```

The button must remain easy to locate and operate at every supported viewport size.

## Filters

The page provides server-driven filters.

| Filter | Control | Query parameter | Default |
| --- | --- | --- | --- |
| `Sala` | Select | `room_id` | All rooms (omit `room_id`) |
| `Status` | Select | `status` (`all`, `active`, `cancelled`) | Ativas (`active`; omit `status`) |
| `Período` | Radio group | `period` (`all`, `today`, `tomorrow`, `week`) | `Hoje` (`today`) |
| `Data inicial` / `Data final` | Date inputs | `starts_on` / `ends_on` (`Y-m-d`) | Empty (period presets apply) |

Example URL:

```text
/reservations?room_id=1&period=today&status=all
```

Past calendar days use `Data inicial` and `Data final`. Do not add a past-meetings radio or a `yesterday` period.

### Room filter

The select displays:

```text
Todas as salas
```

followed by the rooms available for filtering.

Requirements:

- selecting a room displays only reservations assigned to that room;
- `Todas as salas` removes the room restriction;
- preserve the selected period, range, and status when the room changes;
- include inactive rooms when they have reservation history so administrators can still locate historical records;
- use the room identifier in the query string, not the room name.

### Status filter

The select is labelled `Status` and displays:

```text
Todas
Ativas
Canceladas
```

Requirements:

- `Ativas` (`status=active`, omitted from the URL) lists only rows with `cancelled_at` null;
- `Canceladas` (`status=cancelled`) lists only rows with `cancelled_at` set;
- `Todas` (`status=all`) lists both active and cancelled rows that also match room, period, and range;
- omitted `status` on `GET /reservations` is treated as `active`;
- preserve room, period, range, and `limit` when Status changes, and reset `page` to 1.

### Period and range filters

The Período radios are `Todos`, `Hoje`, `Amanhã`, and `1 semana` (`period=all|today|tomorrow|week`). Omitted `period` is `today` (`Hoje`). `Data inicial` and `Data final` send `starts_on` and `ends_on` as `Y-m-d`. A complete range overrides the period preset for the `starts_at` window.

Requirements:

- selecting a preset displays reservations whose start belongs to that local window;
- a complete range is inclusive of both calendar days;
- preserve the room and status selection when the period or range changes;
- interpret dates using the application's configured timezone;
- never compare dates using an accidental UTC boundary;
- update the results through Inertia while preserving scroll and component state where appropriate.

### Filter behavior

- store active filters in the URL so the view can be refreshed and shared;
- reset pagination to `page=1` whenever a filter changes and keep the current `limit`;
- use server-side filtering as the authoritative implementation;
- debounce only controls that benefit from it; selects and date inputs may apply immediately;
- display the active values after navigation or validation failures.

## Reservations table

The table displays one row per reservation.

| Column | Source | Display rules |
| --- | --- | --- |
| `ID` | Reservation identifier | Display the identifier returned by the backend |
| `Sala` | Related room | Display the room name |
| `Responsável` | Responsible person | Display the submitted responsible person's name |
| `Título` | Meeting title or purpose | Plain text with safe wrapping or truncation |
| `Início` | Start datetime | `HH:mm` when a day is selected |
| `Fim` | End datetime | `HH:mm` when a day is selected |
| `Participantes` | Participant count | Positive integer |
| `Situação` | Reservation state | Text badge |
| `Ações` | Available operations | `Editar` and `Cancelar` for eligible active reservations |

If the interface later allows the day filter to be cleared, `Início` and `Fim` must include the date as `DD/MM/YYYY HH:mm` to avoid ambiguity.

## Chronological ordering

Reservations must be ordered by start datetime in ascending order within the selected filters:

```text
start_at ASC
```

This places the earliest reservation of the selected day first and the later reservations afterward. Reservations with the same start time must use a deterministic secondary order, such as reservation ID.

Canceled reservations appear in this list when Status is `Todas` or `Canceladas`. Default Ativas still hides them. Cancellation is not a hard delete: the record remains so the interval is free (RF12). Occupancy queries keep ignoring `cancelled_at` rows.

## Status badges

The status column is derived from `cancelledAt`:

| Backend value | Visible label | Visual treatment |
| --- | --- | --- |
| `active` | `Ativa` | Green text on a light-green background |
| `cancelled` | `Cancelada` | Slate/gray text on a light-gray background |

Default Ativas hides cancelled rows. With Todas or Canceladas, cancelled rows stay visible as `Cancelada`.

If an active reservation has already ended, it continues to use `Ativa`. Do not invent a completed status unless it is explicitly modeled and documented.

## Row actions

### Edit reservation

Active reservations display the link:

```text
Editar
```

It navigates to `/reservations/{id}/edit`. That screen changes only `title` and `responsible`. Date, time, room, and participants stay locked. This action does not lower `participants` to satisfy a room capacity reduction.

### Cancel reservation

Active reservations that are eligible for cancellation display the button:

```text
Cancelar
```

`Editar` sits beside `Cancelar`. Rows whose `status` is not `active` hide both actions and show `—`.

The cancellation action sets `cancelled_at`; it must not hard-delete the reservation record. On default Ativas the row leaves the list. On Todas it stays as `Cancelada`.

## Cancellation confirmation

Canceling a reservation must always require explicit confirmation in a modal dialog.

Suggested content:

```text
Cancelar reserva?

Tem certeza de que deseja cancelar a reserva da Sala Azul em 21/09/2026, das 09:00 às 09:30?
O horário ficará disponível para uma nova reserva.

Voltar
Cancelar reserva
```

### Confirmation behavior

1. The administrator selects `Cancelar` for an active reservation.
2. The dialog opens with the room, date, start time, and end time.
3. Initial focus moves to `Voltar`, the safest action.
4. Selecting `Voltar` or pressing `Escape` closes the dialog without changing data.
5. Selecting `Cancelar reserva` sends `PATCH /reservations/{reservation}/cancel`.
6. While processing, dialog controls are disabled and the destructive action changes to `Cancelando...`.
7. After success, the dialog closes. On Ativas the row leaves the list; on Todas it remains as `Cancelada`.
8. The canceled interval immediately becomes available for a new reservation.
9. Focus returns to an appropriate position in the updated table and the result is announced.

Suggested success message:

```text
Reserva cancelada com sucesso. O horário está disponível novamente.
```

### Cancellation consistency

The backend must perform cancellation consistently:

- lock or atomically update the selected reservation;
- change only an active reservation to canceled;
- treat a repeated cancellation request safely;
- preserve the reservation record for history;
- ensure canceled reservations are excluded from conflict checks;
- prevent partial changes if the operation fails.

Reservations canceled as part of room deactivation or room deletion follow the same rule: they leave the default Ativas list, stay visible as `Cancelada` when Status is Todas, and stop blocking the interval.

## Empty and filtered states

### No reservations registered

When no reservation exists:

```text
Nenhuma reserva cadastrada.
Crie a primeira reserva para começar a organizar as salas.

Nova reserva
```

### No filter results

When reservations exist (including cancelled-only catalogues) but none match the selected filters:

```text
Nenhuma reserva encontrada para os filtros selecionados.
```

Provide a `Limpar filtros` action that visits `period=today` and omits `status`, `room_id`, and the date range.

## Loading and error states

### Loading

For filter changes, pagination, or cancellation:

- preserve the page structure;
- display a subtle progress indicator or table skeleton;
- prevent duplicate cancellation requests;
- avoid large layout shifts;
- keep filter values visible.

### Unexpected load failure

Display:

```text
Não foi possível carregar as reservas.
Tente novamente.
```

Provide a retry action when the request can be repeated safely.

### Cancellation failure

Display the backend message near the dialog or in an accessible alert:

```text
Não foi possível cancelar a reserva. Tente novamente.
```

Keep the reservation active when cancellation fails.

### Unauthorized session

If the session expires or the user is unauthenticated, redirect to `/login`. The backend must protect the page and mutation routes independently of frontend checks.

## Pagination

Use server-driven pagination when the filtered `total` is greater than `limit`. Query params are `page` (default 1, min 1) and `limit` (default 20, min 1, max 100). The Inertia `reservations` prop is only `{ data, page, limit, total }`. See [ADR-010](../adr/010-envelope-simples-de-paginacao-page-e-limit.md).

Requirements:

- preserve `room_id`, `period`, `starts_on`, `ends_on`, and `status` across pages, plus `page` and `limit` (`status` is omitted when Ativas);
- reset to `page=1` when a filter changes and keep the current `limit`;
- expose previous and next controls accessibly;
- retain chronological ordering across all pages.

## Responsiveness

### Desktop and landscape tablet

- display the expanded sidebar;
- keep the top bar above the content;
- align the room and date filters horizontally;
- keep `Nova reserva` aligned to the right;
- display all required table columns;
- keep actions aligned on the right.

### Portrait tablet

- allow the sidebar to collapse into an icon rail or drawer;
- allow filters to wrap without losing labels;
- reduce nonessential horizontal spacing;
- provide controlled horizontal table scrolling when necessary.

### Mobile

- move the sidebar into an accessible navigation drawer;
- stack the heading, filters, and `Nova reserva` action;
- render reservations as responsive cards or use a horizontally scrollable table;
- preserve every required reservation field;
- keep cancellation controls large enough for touch;
- avoid horizontal overflow on the page itself.

## Accessibility

- use semantic navigation landmarks for the sidebar and account menu;
- use explicit labels for the room, status, period, and date filters;
- use a real table with associated column headers on larger layouts;
- provide accessible names for icon-only buttons;
- expose status through text, not color alone;
- provide visible keyboard focus for all interactive controls;
- trap focus inside the cancellation dialog;
- return focus to the triggering control when a dialog closes;
- announce filter results, cancellation results, and errors through an accessible live region;
- maintain sufficient contrast for text, badges, borders, and buttons.

## Visual guidelines

| Element | Guideline |
| --- | --- |
| Application background | Very light blue-gray |
| Sidebar | Dark navy with white and muted-blue text |
| Active navigation item | Lighter blue overlay with white text |
| Filter container | White surface with subtle border or shadow |
| Table container | White, rounded corners, light border, and subtle shadow |
| Table header | Very light gray-blue background |
| Primary button | Bright blue with white text |
| Active badge | Light-green background and green text |
| Canceled badge | Light-gray background and gray text |
| Cancel action | Red text on a pale-red background |

## Required states

- populated reservations list;
- active room filter;
- all-rooms filter;
- selected day filter;
- Status Ativas, Todas, and Canceladas;
- empty unfiltered state;
- empty filtered state;
- loading state;
- cancellation dialog open and closed;
- cancellation in progress;
- cancellation successful;
- cancellation failed;
- active reservation row;
- canceled reservation as `Cancelada` with `—` actions (Todas / Canceladas);
- canceled reservation omitted from default Ativas;
- expired or unauthenticated session.

## Acceptance criteria

- [ ] Only authenticated administrators can access `/reservations`.
- [ ] The list displays ID, room, responsible person, title, start, end, participant count, situation (`Situação`), and actions.
- [ ] Reservations are ordered by start datetime in ascending chronological order.
- [ ] The room filter displays only reservations from the selected room.
- [ ] The Status filter defaults to Ativas and lists Todas / Ativas / Canceladas.
- [ ] Omitted `status` is treated as `active`; `all` and `cancelled` isolate those sets.
- [ ] Period presets (`all`, `today`, `tomorrow`, `week`) and `starts_on`/`ends_on` are the only time filters.
- [ ] Room, status, period, and range filters can be combined.
- [ ] Active filters are represented in the URL and preserved during pagination (`status` omitted when Ativas).
- [ ] `Nova reserva` navigates to `/reservations/create`.
- [ ] Default Ativas lists only reservations with `cancelled_at` null.
- [ ] The `Ações` column does not display a calendar or reservation-details icon.
- [ ] Eligible active reservations display `Editar` (to `/reservations/{id}/edit`) beside `Cancelar`.
- [ ] Cancelled rows show `Cancelada`, hide `Editar` and `Cancelar`, and show `—`.
- [ ] Canceling a reservation always requires explicit confirmation.
- [ ] The cancellation dialog identifies the reservation being affected.
- [ ] Canceling sets `cancelled_at` instead of hard-deleting the reservation record.
- [ ] A canceled reservation leaves the default Ativas list, stays as `Cancelada` on Todas, and no longer blocks its time interval.
- [ ] A repeated cancellation request does not corrupt reservation state.
- [ ] Reservations canceled during room deactivation or deletion also leave Ativas and appear as `Cancelada` on Todas.
- [ ] The screen provides appropriate empty, loading, and error states.
- [ ] The authenticated administrator data is not hardcoded.
- [ ] The screen works correctly on desktop, tablet, and mobile.
- [ ] The layout follows `.local/image/screen-reservations-list.png` as its visual reference.
