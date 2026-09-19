# Reservations List Screen

## Overview

This screen allows an authenticated administrator to view, filter, create, and cancel meeting room reservations in the **ReservaSalas** system.

## Visual reference

The implementation must use the following image as a reference for composition, visual identity, hierarchy, spacing, navigation, filters, table structure, status badges, and action placement:

![Visual reference for the reservations list screen](.local/image/screen-reservations-list.png)

Reference file:

```text
.local/image/screen-reservations-list.png
```

> The image guides the appearance of the screen, but its exact dimensions do not need to be reproduced. The layout must adapt responsively to the available space while preserving the behaviors, required data, business rules, and accessibility requirements defined in this document.

> The reference image includes a calendar icon in each active reservation row. The implementation must omit this icon because the challenge does not require a reservation details or edit action. The `Ações` column contains only the cancellation action when it is available.

- **Page route:** `GET /reservations`
- **Access:** authenticated administrators only
- **Create route:** `GET /reservations/create`
- **Cancel route:** `PATCH /reservations/{reservation}/cancel`
- **Expected technologies:** React, Inertia.js 2, Tailwind CSS, and Laravel

## Goal

Provide a central administrative view where the user can:

- view room reservations in chronological order;
- filter reservations by room;
- filter reservations by day;
- identify active and canceled reservations;
- open the new reservation form;
- cancel an active reservation after confirmation;
- verify that a canceled reservation no longer blocks its time interval.

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

The page provides two server-driven filters.

| Filter | Control | Query parameter | Default |
| --- | --- | --- | --- |
| `Sala` | Select | `room_id` | All rooms |
| `Data` | Date input | `date` | Current local date |

Example URL:

```text
/reservations?room_id=1&date=2026-09-21
```

### Room filter

The select displays:

```text
Todas as salas
```

followed by the rooms available for filtering.

Requirements:

- selecting a room displays only reservations assigned to that room;
- `Todas as salas` removes the room restriction;
- preserve the selected day when the room changes;
- include inactive rooms when they have reservation history so administrators can still locate historical records;
- use the room identifier in the query string, not the room name.

### Day filter

The date input uses the visible Brazilian format `DD/MM/YYYY`, while the query string uses `YYYY-MM-DD`.

Requirements:

- selecting a date displays reservations whose start belongs to that local calendar day;
- preserve the room selection when the day changes;
- interpret the day using the application's configured timezone;
- never compare dates using an accidental UTC boundary;
- update the results through Inertia while preserving scroll and component state where appropriate.

### Filter behavior

- store active filters in the URL so the view can be refreshed and shared;
- reset pagination to the first page whenever a filter changes;
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
| `Status` | Reservation state | Text badge |
| `Ações` | Available operations | Cancel action for eligible active reservations |

If the interface later allows the day filter to be cleared, `Início` and `Fim` must include the date as `DD/MM/YYYY HH:mm` to avoid ambiguity.

## Chronological ordering

Reservations must be ordered by start datetime in ascending order within the selected filters:

```text
start_at ASC
```

This places the earliest reservation of the selected day first and the later reservations afterward. Reservations with the same start time must use a deterministic secondary order, such as reservation ID.

Canceled reservations remain in their chronological position and must not disappear from the history merely because they no longer block the room.

## Status badges

The interface supports at least these states:

| Backend value | Visible label | Visual treatment |
| --- | --- | --- |
| `active` | `Ativa` | Green text on a light-green background |
| `canceled` | `Cancelada` | Gray text on a light-gray background |

Color must not be the only way status is communicated. The visible status text is mandatory.

If an active reservation has already ended, it may continue to use `Ativa` if the domain model only distinguishes active and canceled. Do not invent a completed status unless it is explicitly modeled and documented.

## Row actions

### Cancel reservation

Active reservations that are eligible for cancellation display the button:

```text
Cancelar
```

Canceled reservations display a dash or unavailable state instead of another cancel button.

The cancellation action must change the reservation status; it must not permanently delete the reservation record.

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
7. After success, the dialog closes and the row status changes to `Cancelada`.
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

Reservations canceled as part of room deactivation must follow the same status and availability rules and appear as `Cancelada` in this list.

## Empty and filtered states

### No reservations registered

When no reservation exists:

```text
Nenhuma reserva cadastrada.
Crie a primeira reserva para começar a organizar as salas.

Nova reserva
```

### No filter results

When reservations exist but none match the selected room and day:

```text
Nenhuma reserva encontrada para os filtros selecionados.
```

Provide a `Limpar filtros` action that restores `Todas as salas` and the default day.

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

Use server-driven pagination when the number of filtered reservations exceeds the configured page size.

Requirements:

- preserve `room_id` and `date` across pages;
- reset to page one when a filter changes;
- expose previous, next, and available page controls accessibly;
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
- use explicit labels for both filters;
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
- empty unfiltered state;
- empty filtered state;
- loading state;
- cancellation dialog open and closed;
- cancellation in progress;
- cancellation successful;
- cancellation failed;
- active reservation row;
- canceled reservation row;
- expired or unauthenticated session.

## Acceptance criteria

- [ ] Only authenticated administrators can access `/reservations`.
- [ ] The list displays ID, room, responsible person, title, start, end, participant count, status, and actions.
- [ ] Reservations are ordered by start datetime in ascending chronological order.
- [ ] The room filter displays only reservations from the selected room.
- [ ] The day filter displays only reservations belonging to the selected local calendar day.
- [ ] Room and day filters can be combined.
- [ ] Active filters are represented in the URL and preserved during pagination.
- [ ] `Nova reserva` navigates to `/reservations/create`.
- [ ] Active and canceled reservations use distinct visible labels and badges.
- [ ] The `Ações` column does not display a calendar or reservation-details icon.
- [ ] Eligible active reservations display only the `Cancelar` action.
- [ ] Canceled reservations display no action and use a dash or equivalent unavailable state.
- [ ] Canceling a reservation always requires explicit confirmation.
- [ ] The cancellation dialog identifies the reservation being affected.
- [ ] Canceling changes the status instead of deleting the reservation record.
- [ ] A canceled reservation no longer blocks its time interval.
- [ ] A repeated cancellation request does not corrupt reservation state.
- [ ] Canceled reservations remain visible in the list as historical records.
- [ ] Reservations canceled during room deactivation also appear as canceled.
- [ ] The screen provides appropriate empty, loading, and error states.
- [ ] The authenticated administrator data is not hardcoded.
- [ ] The screen works correctly on desktop, tablet, and mobile.
- [ ] The layout follows `.local/image/screen-reservations-list.png` as its visual reference.
