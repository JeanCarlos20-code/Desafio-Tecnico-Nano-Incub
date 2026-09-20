# Rooms List Screen

## Overview

This screen allows an authenticated administrator to view and manage all meeting rooms registered in the **ReservaSalas** system.

## Visual reference

The implementation must use the following image as a reference for composition, visual identity, hierarchy, spacing, navigation, table structure, and action placement:

![Visual reference for the rooms list screen](.local/image/screen-rooms-list.png)

Reference file:

```text
.local/image/screen-rooms-list.png
```

> The image guides the appearance of the screen, but its exact dimensions do not need to be reproduced. The layout must adapt responsively to the available space while preserving the behaviors, data requirements, and accessibility rules defined in this document.

- **Page route:** `GET /rooms`
- **Access:** authenticated administrators only
- **Create route:** `GET /rooms/create`
- **Edit route:** `GET /rooms/{room}/edit`
- **Delete route:** `DELETE /rooms/{room}`
- **Expected technologies:** React, Inertia.js 2, Tailwind CSS, and Laravel

## Goal

Provide a clear administrative view where the user can:

- see all registered rooms;
- identify each room's capacity and current status;
- open the room creation form;
- open the room editing form;
- delete a room after explicit confirmation.

The room data must come from the backend. The interface must not reproduce the example rows as fixed frontend content.

## Page structure

The screen uses the authenticated administrative layout shared by the rooms and reservations sections.

### Sidebar

The fixed or collapsible sidebar appears on the left and uses a dark navy background.

Elements:

1. `ReservaSalas` wordmark with a calendar icon.
2. Navigation item `Reservas`.
3. Navigation item `Salas` in the active state.

Navigation behavior:

| Item | Destination |
| --- | --- |
| `Reservas` | `/reservations` |
| `Salas` | `/rooms` |

The active item must be communicated visually and programmatically using `aria-current="page"`.

### Top bar

The top bar occupies the content area to the right of the sidebar.

Elements:

- signed-in administrator avatar or initials;
- administrator name, such as `João Silva`;
- dropdown indicator;
- account menu containing at least the logout action.

The name and initials must be generated from the authenticated user data and must not be hardcoded.

### Main content header

The content header contains:

- title `Salas`;
- supporting text `Cadastre e gerencie as salas de reunião.`;
- primary button `Nova sala` with a plus icon.

On desktop, the title block stays on the left and the primary action stays on the right. On smaller screens, they may stack vertically.

## Status filter

The page provides one server-driven filter so the administrator can see active rooms, inactive rooms, or both. Soft-deleted rooms never appear.

| Filter | Control | Query parameter | Default |
| --- | --- | --- | --- |
| `Status` | Select | `status` | `all` (`Todas`) |

| Visible label | Query value | Result |
| --- | --- | --- |
| `Todas` | `all` or omitted | Active and inactive rooms |
| `Ativas` | `active` | Only `is_active = true` |
| `Inativas` | `inactive` | Only `is_active = false` |

Example URL:

```text
/rooms?status=inactive
```

Requirements:

- store the filter in the URL so the view can be refreshed and shared;
- reset pagination to the first page when the filter changes;
- use server-side filtering as the authoritative implementation;
- keep the selected value after navigation or validation failures;
- label the control explicitly (`Status`).

## Rooms table

The table displays one row per room.

| Column | Source | Display rules |
| --- | --- | --- |
| `Nome` | Room name | Plain text; do not silently truncate short names. This column stays the widest (`w-full` + `min-w-0`, no max-width, no `xl:w-[16%]`). |
| `Capacidade` | Room capacity | Positive integer centered with the `Capacidade` header (`text-center`). Stay compact with `w-0` below `xl`. At Tailwind `xl` and above, share leftover width with `xl:w-[16%]`. |
| `Status` | Active state | Colored status badge. Stay compact with `w-0` below `xl`. At Tailwind `xl` and above, share leftover width with `xl:w-[16%]`. |
| `Criada em` | Creation timestamp | Format as `DD/MM/YYYY` in the configured application timezone. Stay compact with `w-0` below `xl`. At Tailwind `xl` and above, share leftover width with `xl:w-[16%]`. |
| `Ações` | Available operations | Edit and delete icon buttons centered with the `Ações` header (`text-center`, not `text-right`). Stay compact with `w-0` below `xl` so this column does not grow larger than `Nome`. At Tailwind `xl` and above, share leftover width with `xl:w-[16%]`. |

The room identifier is not shown in the list or form. Keep it in persistence, Inertia props, React keys, and `/rooms/{room}` routes.

### Status badge

The status values are presented in Portuguese:

| Backend value | Visible label | Visual treatment |
| --- | --- | --- |
| `active` or `true` | `Ativa` | Green text on a light-green background |
| `inactive` or `false` | `Inativa` | Gray text on a light-gray background |

Color must not be the only way status is communicated. The visible text is mandatory.

### Row actions

Each row contains two compact icon buttons:

| Action | Icon | Behavior |
| --- | --- | --- |
| Edit | Pencil | Navigates to `/rooms/{room}/edit` |
| Delete | Trash | Opens the deletion confirmation dialog |

Every icon-only button must include an accessible label containing the room name, for example:

```text
Editar Sala Azul
Excluir Sala Azul
```

## Create room action

Selecting `Nova sala` navigates to:

```text
/rooms/create
```

The button must remain easy to find at all supported viewport sizes.

## Delete confirmation

Deleting a room is a destructive action and must always require explicit confirmation.

The confirmation should be displayed as a modal dialog rather than a separate page.

Suggested content when the room has no reservations:

```text
Excluir sala?

Tem certeza de que deseja excluir a sala “Sala Azul”?
Esta ação não poderá ser desfeita.

Cancelar
Excluir sala
```

When the room has reservations, the dialog **must** warn that active meetings will be canceled. There is no keep-meetings choice on delete.

```text
Excluir sala?

Tem certeza de que deseja excluir a sala “Sala Azul”?
As reuniões ativas desta sala serão canceladas e deixarão de aparecer na listagem.
Esta ação não poderá ser desfeita.

Cancelar
Excluir sala
```

### Confirmation behavior

1. The administrator selects the delete action for a room.
2. The dialog opens and includes the room name.
3. Initial focus moves to the safest action, `Cancelar`.
4. Selecting `Cancelar`, pressing `Escape`, or closing the dialog returns focus to the delete button that opened it.
5. Selecting `Excluir sala` sends `DELETE /rooms/{room}`.
6. While processing, both dialog actions are disabled and the destructive button changes to `Excluindo...`.
7. After success, the dialog closes, the row is removed from the current result, and a success message is announced.

Suggested success message:

```text
Sala excluída com sucesso.
```

### Room with reservations

Deletion is allowed. The backend cancels every active reservation of that room (`cancelled_at`) and soft-deletes the room in the **same transaction**, with a `SELECT … FOR UPDATE` lock on the room (ADR-006). Canceled meetings leave the reservations list.

The dialog warning is mandatory when any reservation exists for the room. The backend must still cancel and delete atomically if the request arrives without the UI warning.

## Data ordering and pagination

The challenge does not define a mandatory room ordering. Use a deterministic default order:

1. room ID in ascending order; or
2. room name in ascending alphabetical order.

The selected rule must remain consistent between requests.

If the number of rooms exceeds the configured page size, display server-driven pagination below the table. Pagination links must preserve the current query parameters.

## Page behavior

### Successful load

- render all room rows returned by the backend;
- display the appropriate status badge;
- format creation dates consistently;
- enable create, edit, and delete actions.

### Empty state

When no rooms exist, replace the table body with an empty state:

```text
Nenhuma sala cadastrada.
Cadastre a primeira sala para começar a organizar as reservas.

Nova sala
```

The empty-state button navigates to `/rooms/create`.

### Loading state

For client-side navigation or reloads:

- preserve the page structure;
- indicate that the room list is loading;
- prevent duplicate destructive actions;
- avoid showing stale row actions as available while a mutation is being processed.

A subtle progress indicator or table skeleton may be used without causing large layout shifts.

### Unexpected failure

If the room list cannot be loaded, display:

```text
Não foi possível carregar as salas.
Tente novamente.
```

Provide a retry action when the failure can be retried safely.

### Unauthorized session

If the session expires or the user is unauthenticated, redirect to `/login`. The backend must protect the route independently of any frontend checks.

## Feedback messages

Messages returned after create, edit, or delete operations should be displayed as dismissible flash notifications.

Examples:

```text
Sala criada com sucesso.
Sala atualizada com sucesso.
Sala excluída com sucesso.
```

Flash messages must be announced through an accessible live region and must not be stored permanently in the page state.

## Responsiveness

### Desktop and landscape tablet

- display the expanded sidebar;
- keep the top bar above the main content;
- show all table columns;
- keep leftover-width priority on `Nome` via `w-full` + `min-w-0` and no max-width;
- center each capacity integer under the `Capacidade` header with shared `text-center`;
- at Tailwind `xl` (1280px) and above, give `Capacidade`, `Status`, `Criada em`, and `Ações` a leftover share (`xl:w-[16%]` each) so the name gutter stays smaller;
- below `xl`, keep those four columns compact with `w-0` + `whitespace-nowrap` so `Nome` stays the largest column and `Ações` does not stretch;
- center the `Ações` header and row actions (`text-center`);
- keep the desktop table as `table-auto` inside `overflow-x-auto`;
- preserve comfortable horizontal spacing around the table.

### Portrait tablet

- allow the sidebar to collapse into an icon rail or drawer;
- reduce nonessential horizontal spacing;
- keep all required room fields accessible;
- allow controlled horizontal table scrolling if necessary.

### Mobile

- move the sidebar into an accessible navigation drawer;
- stack the page title and `Nova sala` button;
- render rooms as responsive cards or provide a horizontally scrollable table;
- never remove any required room information;
- keep edit and delete actions large enough for touch interaction;
- prevent the page itself from overflowing horizontally.

## Accessibility

- use semantic navigation landmarks for the sidebar and account menu;
- use a real table with a caption available to assistive technologies on larger layouts;
- associate table headers with their respective columns;
- provide visible keyboard focus for all interactive controls;
- use accessible labels for icon-only actions;
- expose status through text, not color alone;
- trap focus inside the deletion dialog while it is open;
- return focus to the triggering control after the dialog closes;
- announce flash messages and asynchronous errors with `aria-live`;
- keep color contrast compliant for text, badges, borders, and buttons.

## Visual guidelines

| Element | Guideline |
| --- | --- |
| Application background | Very light blue-gray |
| Sidebar | Dark navy with white and muted-blue text |
| Active navigation item | Lighter blue overlay with white text |
| Content surface | White or near-white |
| Primary button | Bright blue with white text |
| Table container | White, rounded corners, light border, and subtle shadow |
| Table header | Very light gray-blue background |
| Active badge | Light-green background and green text |
| Inactive badge | Light-gray background and gray text |
| Edit action | Blue icon on a pale-blue background |
| Delete action | Red icon on a pale-red background |

## Required states

- populated room list;
- all-status filter;
- active-only filter;
- inactive-only filter;
- empty room list;
- empty filtered list;
- loading state;
- unexpected load failure;
- account menu open and closed;
- delete confirmation open and closed;
- delete confirmation with reservation-cancellation warning;
- deletion in progress;
- deletion successful;
- expired or unauthenticated session.

## Acceptance criteria

- [ ] Only authenticated administrators can access `/rooms`.
- [ ] The list displays name, capacity, status, creation date, and actions for every room. The room identifier is not shown in the list or form.
- [ ] Active and inactive rooms use distinct text labels and visual badges.
- [ ] The status filter supports `Todas`, `Ativas`, and `Inativas`, is stored in the URL, and is applied on the server.
- [ ] Soft-deleted rooms never appear in this list.
- [ ] Creation dates are displayed as `DD/MM/YYYY`.
- [ ] `Nova sala` navigates to `/rooms/create`.
- [ ] The edit action navigates to `/rooms/{room}/edit`.
- [ ] The delete action always opens a confirmation dialog before sending a request.
- [ ] When the room has reservations, the dialog warns that active meetings will be canceled.
- [ ] Canceling the dialog does not modify the room or its reservations.
- [ ] Confirming deletion sends `DELETE /rooms/{room}` only once.
- [ ] Deletion cancels active reservations and soft-deletes the room in one transaction.
- [ ] Deletion success and failure messages are visible and accessible.
- [ ] The screen provides an appropriate empty state when no rooms exist.
- [ ] The sidebar correctly marks `Salas` as the current page.
- [ ] The authenticated administrator data is not hardcoded.
- [ ] The layout works correctly on desktop, tablet, and mobile.
- [ ] The layout follows `.local/image/screen-rooms-list.png` as its visual reference.
