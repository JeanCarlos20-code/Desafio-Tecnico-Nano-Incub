# Create Reservation Screen

## Overview

This screen allows an authenticated administrator to create a meeting room reservation in the **ReservaSalas** system.

It is the main entry point for the reservation business rules defined by the challenge. Visual validation may improve feedback, but Laravel must enforce every authoritative rule on the server.

## Visual reference

The implementation must use the following image as a reference for composition, visual identity, hierarchy, spacing, navigation, field placement, and action placement:

![Visual reference for the create reservation screen](.local/image/screen-reservation-create.png)

Reference file:

```text
.local/image/screen-reservation-create.png
```

> The image guides the appearance of the screen, but its exact dimensions do not need to be reproduced. The layout must adapt responsively to the available space while preserving all validation, consistency, concurrency, and accessibility requirements defined in this document.

- **Page route:** `GET /reservations/create`
- **Submission route:** `POST /reservations`
- **Access:** authenticated administrators only
- **Success destination:** `/reservations`
- **Expected technologies:** React, Inertia.js 2, Tailwind CSS, Laravel, and MySQL 8

## Goal

Create an active reservation containing:

- room;
- responsible person's name;
- meeting title or purpose;
- date;
- start time;
- end time;
- number of participants.

The form does not include a status field. Every successfully created reservation starts with the active status.

## Shared administrative layout

The screen uses the same authenticated layout as the rooms and reservations lists.

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

## Main content

The content area contains:

```text
Nova reserva
Preencha os dados da reserva.
```

The form is displayed inside a white card. On desktop, fields are arranged in two columns. On mobile, they are stacked into a single column.

## Form fields

| Field | Input | Required | Example |
| --- | --- | --- | --- |
| `Sala` | Select | Yes | `Sala Azul` |
| `Responsável` | Text | Yes | `Jean Carlos` |
| `Título / finalidade` | Text | Yes | `Reunião de planejamento` |
| `Data` | Date | Yes | `21/09/2026` |
| `Horário de início` | Time | Yes | `09:00` |
| `Horário de término` | Time | Yes | `10:00` |
| `Participantes` | Number | Yes | `6` |

All required labels include a visible red asterisk and must also be marked programmatically.

## Field behavior and validation

### Room

Visible label:

```text
Sala
```

Requirements:

- list active rooms only;
- submit the selected room identifier, never its name;
- preserve the selected value after a validation error;
- revalidate the room status and existence on the server during submission;
- reject a stale submission if the room was deactivated after the form loaded.

An option may show capacity to help the administrator choose correctly:

```text
Sala Azul — capacidade para 8 pessoas
```

If no active room exists, disable submission and display:

```text
Nenhuma sala ativa está disponível.
Cadastre ou reative uma sala antes de criar uma reserva.
```

Provide a link to `/rooms` or `/rooms/create` when appropriate.

### Responsible person

Visible label:

```text
Responsável
```

Requirements:

- use a text input;
- trim leading and trailing whitespace;
- preserve internal spaces;
- require a nonempty value;
- use a maximum length compatible with the database column.

Recommended Laravel validation:

```text
required|string|max:255
```

### Title or purpose

Visible label:

```text
Título / finalidade
```

Requirements:

- use a text input;
- trim leading and trailing whitespace;
- require a nonempty value;
- use a maximum length compatible with the database column.

Recommended Laravel validation:

```text
required|string|max:255
```

### Date

Visible label:

```text
Data
```

Requirements:

- display dates as `DD/MM/YYYY`;
- submit an unambiguous value such as `YYYY-MM-DD`;
- interpret the date using the application's configured timezone;
- combine it with the start and end times on the server;
- reject the reservation if the resulting start datetime has already occurred.

The date alone is not sufficient to validate the past rule. A reservation for today is valid only when its complete start datetime is still in the future.

### Start time

Visible label:

```text
Horário de início
```

Requirements:

- use a time input or accessible time picker;
- submit an unambiguous 24-hour value such as `09:00`;
- combine it with the selected date on the server;
- reject a resulting start datetime in the past.

### End time

Visible label:

```text
Horário de término
```

Requirements:

- use a time input or accessible time picker;
- submit an unambiguous 24-hour value such as `10:00`;
- combine it with the selected date on the server;
- require the resulting end datetime to be later than the start datetime.

Because this design provides one date for both times, reservations are modeled as starting and ending on the same calendar day. This decision should be documented in the `README.md` because the challenge supplies separate start and end datetimes but does not explicitly define overnight reservations.

### Participants

Visible label:

```text
Participantes
```

Requirements:

- use a numeric input;
- accept integers only;
- require at least one participant;
- do not silently round decimal values;
- reject values greater than the selected room's current capacity;
- revalidate capacity on the server because it may have changed after the form loaded.

Recommended basic Laravel validation:

```text
required|integer|min:1
```

The capacity comparison is a business rule and must be checked against the selected room record.

## Suggested submission payload

```json
{
  "room_id": 1,
  "responsible_name": "Jean Carlos",
  "title": "Reunião de planejamento",
  "date": "2026-09-21",
  "start_time": "09:00",
  "end_time": "10:00",
  "participants": 6
}
```

The backend combines `date`, `start_time`, and `end_time` into authoritative datetime values before applying the business rules and storing the reservation.

## Business rules

### Active room

- the selected room must exist;
- the selected room must be active when the reservation is committed;
- an inactive room cannot receive a new reservation.

Suggested message:

```text
A sala selecionada está inativa e não pode receber novas reservas.
```

### Start and end order

The end datetime must be later than the start datetime.

Invalid examples:

```text
09:00 → 09:00
10:00 → 09:00
```

Suggested message:

```text
O horário de término deve ser posterior ao horário de início.
```

### Duration

- minimum duration: 30 minutes;
- maximum duration: 4 hours.

Boundary examples:

| Interval | Result |
| --- | --- |
| `09:00–09:29` | Rejected |
| `09:00–09:30` | Accepted |
| `09:00–13:00` | Accepted |
| `09:00–13:01` | Rejected |

Suggested messages:

```text
A reserva deve possuir duração mínima de 30 minutos.
```

```text
A reserva deve possuir duração máxima de 4 horas.
```

### Room capacity

The participant count must not exceed the room's current capacity.

Suggested message:

```text
A Sala Azul possui capacidade máxima para 8 participantes.
```

### Reservation in the past

The start datetime must be later than the current time according to the application's configured timezone.

Suggested message:

```text
Não é possível criar uma reserva com horário de início no passado.
```

### Time conflict

Two active reservations for the same room cannot overlap, even partially.

For an existing active reservation and a new reservation, a conflict exists when:

```text
existing.start_at < new.end_at
AND
existing.end_at > new.start_at
```

This rule rejects:

- a new reservation completely inside an existing reservation;
- a new reservation that completely contains an existing reservation;
- a new reservation that starts during an existing reservation;
- a new reservation that ends during an existing reservation;
- reservations with identical start and end datetimes.

Only active reservations participate in the conflict check. Canceled reservations do not block their previous intervals.

Suggested message:

```text
Já existe uma reserva ativa para a Sala Azul nesse intervalo.
```

### Consecutive reservations

Reservations that only touch at a boundary are valid:

```text
Existing reservation: 09:00–10:00
New reservation:      10:00–11:00
Result:               Accepted
```

The conflict implementation must use strict comparisons so the end of one reservation may equal the start of another.

## Concurrency and consistency

Conflict validation and reservation insertion must form one consistent operation. A frontend availability check alone is not sufficient.

Recommended MySQL 8 flow:

1. Begin a database transaction.
2. Lock the selected room row using `SELECT ... FOR UPDATE`.
3. Revalidate that the room exists and is active.
4. Revalidate the room capacity.
5. Query active reservations for the room using the overlap condition.
6. Reject the request if any conflict exists.
7. Insert the active reservation.
8. Commit the transaction.

Locking the room row provides one serialization point for reservation creation in that room. Every code path that creates a reservation must follow the same locking protocol.

If the transaction fails, no reservation may be partially created. Two simultaneous requests for overlapping intervals in the same room must result in one success and one clear conflict response, never two active overlapping reservations.

The chosen concurrency strategy and its reasoning must be explained in the `README.md`.

## Form actions

The actions appear at the bottom-right of the form card on desktop.

| Action | Behavior |
| --- | --- |
| `Cancelar` | Returns to `/reservations` without submitting |
| `Criar reserva` | Submits the form |

### Cancel behavior

- navigate to `/reservations` without creating data;
- if the form contains unsaved values, an optional confirmation may be shown;
- canceling must never submit the form.

## Submission behavior

### Successful creation

1. The administrator fills in every required field.
2. The frontend submits `POST /reservations` through Inertia.
3. Laravel performs input validation.
4. The backend combines and normalizes the datetime values.
5. The backend applies all business rules inside the protected creation flow.
6. The reservation is stored with active status.
7. The administrator is redirected to the reservation list, preferably filtered to the created reservation's day and room.
8. A success flash message is displayed.

Suggested message:

```text
Reserva criada com sucesso.
```

Suggested redirect:

```text
/reservations?room_id=1&date=2026-09-21
```

### Validation or business-rule failure

- remain on the create screen;
- preserve every valid submitted value;
- display field-specific validation errors next to their fields;
- display cross-field or conflict errors in a visible form-level alert;
- move focus to the first invalid field or the error summary;
- do not create any reservation.

Suggested required-field messages:

```text
Selecione uma sala.
Informe o responsável.
Informe o título ou a finalidade da reunião.
Informe a data da reserva.
Informe o horário de início.
Informe o horário de término.
Informe a quantidade de participantes.
```

## Loading and processing state

While submission is in progress:

- disable `Criar reserva` to prevent duplicate submissions;
- disable `Cancelar` if leaving would interrupt the active request;
- keep field values visible;
- change the primary label to `Criando reserva...`;
- display a loading indicator;
- prevent a second form submission.

## Unexpected failure

If an unexpected error occurs, display:

```text
Não foi possível criar a reserva. Tente novamente.
```

Keep the submitted values whenever safely possible. An unexpected failure must not leave a partial reservation in the database.

## Unauthorized session

If the session expires or the user is unauthenticated, redirect to `/login`. The backend must protect both the page and submission routes independently of frontend checks.

## Responsiveness

### Desktop and landscape tablet

- display the expanded sidebar;
- keep the top bar above the content;
- display the form in two columns;
- keep related fields visually aligned;
- align `Cancelar` and `Criar reserva` to the bottom-right.

Recommended desktop arrangement:

| Left column | Right column |
| --- | --- |
| Sala | Horário de início |
| Responsável | Horário de término |
| Título / finalidade | Participantes |
| Data | Empty or reserved for validation feedback |

### Portrait tablet

- allow the sidebar to collapse into an icon rail or drawer;
- keep two columns only when fields retain a comfortable width;
- otherwise switch to one column;
- maintain full-width inputs within their grid area.

### Mobile

- move the sidebar into an accessible navigation drawer;
- display the form as a single column;
- make inputs and buttons fill the available width;
- stack the action buttons when necessary;
- keep date and time pickers usable with touch and the virtual keyboard;
- prevent horizontal scrolling.

## Accessibility

- associate every label with its input;
- mark required fields visually and programmatically;
- provide visible keyboard focus for fields, selects, buttons, and navigation;
- ensure date and time controls remain keyboard accessible;
- connect validation errors using `aria-invalid` and `aria-describedby`;
- use an accessible alert for conflict and cross-field errors;
- announce submission results through an accessible live region;
- maintain logical reading and tab order despite the two-column visual layout;
- do not rely only on color to communicate errors;
- keep button and input contrast compliant.

## Visual guidelines

| Element | Guideline |
| --- | --- |
| Application background | Very light blue-gray |
| Sidebar | Dark navy with white and muted-blue text |
| Active navigation item | Lighter blue overlay with white text |
| Form card | White, rounded corners, light border, and subtle shadow |
| Labels | Dark navy text with red required indicators |
| Fields | White background, light-gray border, and rounded corners |
| Date and time icons | Navy or blue-gray |
| Primary button | Bright blue with white text |
| Secondary button | White or light background with a subtle border |
| Error text and alerts | Red with sufficient contrast and supporting text or icon |

## Required states

- empty form;
- active rooms available;
- no active rooms available;
- selected room with capacity information;
- focused text, number, date, time, and select fields;
- one or more required-field errors;
- invalid start and end order;
- duration below 30 minutes;
- duration above 4 hours;
- participant count above room capacity;
- inactive room rejected after a stale form submission;
- start datetime in the past;
- total or partial time conflict;
- consecutive reservation accepted;
- submission in progress;
- successful creation;
- unexpected failure;
- expired or unauthenticated session.

## Acceptance criteria

- [ ] Only authenticated administrators can access `/reservations/create` and `POST /reservations`.
- [ ] The form contains room, responsible person, title or purpose, date, start time, end time, and participant count.
- [ ] Every field is required and validated by Laravel on the server.
- [ ] Only active rooms are offered by the select.
- [ ] The backend rejects a room that became inactive after the page loaded.
- [ ] A newly created reservation receives active status automatically.
- [ ] The end datetime must be later than the start datetime.
- [ ] Duration below 30 minutes is rejected.
- [ ] Duration equal to 30 minutes is accepted.
- [ ] Duration equal to 4 hours is accepted.
- [ ] Duration above 4 hours is rejected.
- [ ] Participant count must be a positive integer.
- [ ] Participant count cannot exceed the room's current capacity.
- [ ] A start datetime in the past is rejected.
- [ ] Every total, partial, containing, contained, or identical active overlap is rejected.
- [ ] Consecutive reservations whose boundaries only touch are accepted.
- [ ] Canceled reservations do not cause conflicts.
- [ ] The conflict check and insertion execute as one consistent operation.
- [ ] Two simultaneous overlapping requests cannot both create active reservations.
- [ ] Validation and business-rule errors are displayed clearly without losing valid form values.
- [ ] The form cannot be submitted more than once while processing.
- [ ] `Cancelar` returns to `/reservations` without creating a reservation.
- [ ] Successful creation redirects to the reservation list and displays a success message.
- [ ] The screen works correctly on desktop, tablet, and mobile.
- [ ] The layout follows `.local/image/screen-reservation-create.png` as its visual reference.
