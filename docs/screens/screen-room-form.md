# Room Form Screen

## Overview

This screen allows an authenticated administrator to create a new meeting room or edit an existing room in the **ReservaSalas** system.

The same React form component should be reused for both modes. Its title, initial values, submission route, HTTP method, and success message change according to the current route.

## Visual reference

The implementation must use the following image as a reference for composition, visual identity, hierarchy, spacing, navigation, and form structure:

![Visual reference for the room form screen](.local/image/screen-room-form.png)

Reference file:

```text
.local/image/screen-room-form.png
```

> The image guides the appearance of the screen, but its exact dimensions do not need to be reproduced. The layout must adapt responsively to the available space while preserving the behaviors, validation rules, and accessibility requirements defined in this document.

> The reference image shows the `Status` field in the create form. The implemented create mode must intentionally omit this field. `Status` is displayed only in edit mode, where an existing room can be activated or deactivated.

## Routes and modes

| Mode | Page route | Submission route | Method |
| --- | --- | --- | --- |
| Create | `GET /rooms/create` | `/rooms` | `POST` |
| Edit | `GET /rooms/{room}/edit` | `/rooms/{room}` | `PUT` or `PATCH` |

- **Access:** authenticated administrators only
- **Success destination:** `/rooms`
- **Expected technologies:** React, Inertia.js 2, Tailwind CSS, and Laravel

## Goal

Allow an administrator to define and maintain the essential data of a meeting room:

- name;
- capacity;
- active or inactive status when editing an existing room.

Every newly created room must be active by default. The create form must not ask the administrator to choose a status.

All validation must be enforced by Laravel on the server. The React interface must display the validation errors returned by the backend.

## Shared administrative layout

The screen uses the same authenticated layout as the rooms list and reservations pages.

### Sidebar

The sidebar appears on the left with a dark navy background.

Elements:

1. `ReservaSalas` wordmark with a calendar icon.
2. Navigation item `Reservas`.
3. Navigation item `Salas` in the active state.

Navigation behavior:

| Item | Destination |
| --- | --- |
| `Reservas` | `/reservations` |
| `Salas` | `/rooms` |

The active `Salas` item must be communicated visually and through `aria-current="page"`.

### Top bar

The top bar contains:

- the authenticated administrator's avatar or initials;
- the administrator's name;
- a dropdown indicator;
- an account menu containing at least the logout action.

User information must come from the authenticated session and must not be hardcoded.

## Main content

The content area contains a title, supporting text, and a centered form card.

### Create mode copy

```text
Nova sala
Preencha as informações da sala de reunião.
```

### Edit mode copy

```text
Editar sala
Atualize as informações da sala de reunião.
```

In edit mode, the form must be populated with the current room values returned by the backend.

## Form fields

| Field | Input | Create mode | Edit mode |
| --- | --- | --- | --- |
| `Nome` | Text | Required and empty | Required with the current room name |
| `Capacidade` | Number | Required and empty | Required with the current room capacity |
| `Status` | Select | Not displayed | Required with the current room status |

Required labels include a visible red asterisk and must also be marked programmatically.

### Name

Visible label:

```text
Nome
```

Requirements:

- use a text input;
- trim leading and trailing whitespace before validation and persistence;
- preserve internal spaces;
- display the current value in edit mode;
- use a maximum length compatible with the database column.

Recommended Laravel validation:

```text
required|string|max:255
```

If room names are required to be unique, enforce uniqueness in both the database and Laravel validation. During editing, the current room must be ignored by the uniqueness rule. Because the challenge does not explicitly require unique names, this decision should be documented in the `README.md` if adopted.

### Capacity

Visible label:

```text
Capacidade
```

Requirements:

- use a numeric input;
- accept integers only;
- minimum value of `1`;
- prevent zero and negative capacity;
- do not silently round decimal values;
- display the current value in edit mode.

Recommended Laravel validation:

```text
required|integer|min:1
```

Do not invent an arbitrary maximum capacity unless the chosen database type or business decision requires one. If a maximum is introduced, document it and use the same limit in frontend hints and backend validation.

On edit save, Laravel refuses a lower capacity when this room has one or more future active reservations (`cancelled_at` null, `starts_at` after now) whose `participants` exceed the proposed number. The room row and those reservations stay unchanged. The error is returned on `capacity` and shown on the Capacidade field. One meeting: `Não é possível reduzir a capacidade. Existe 1 reunião marcada com mais participantes do que a nova capacidade. Altere essa reunião primeiro e depois volte.` More than one: `Não é possível reduzir a capacidade. Existem {n} reuniões marcadas com mais participantes do que a nova capacidade. Altere essas reuniões primeiro e depois volte.` Increase, same capacity, and future meetings that already fit are saved as usual. Changing those meetings means cancel (then create again if needed); this screen does not edit reservation participants.

### Status

The `Status` field exists only in edit mode. It must not be rendered or submitted by the create form.

Visible label:

```text
Status
```

The select contains:

| Stored value | Visible label |
| --- | --- |
| `true` or `active` | `Ativa` |
| `false` or `inactive` | `Inativa` |

Requirements:

- display the stored value when editing;
- submit a value that the backend converts and validates explicitly;
- never rely on an unchecked or missing value to infer the room status.

An inactive room remains visible in the rooms list but cannot receive new reservations.

#### Deactivation warning

When the administrator selects `Inativa`, display an inline warning immediately below the status field:

```text
Ao desativar esta sala, novas reservas serão bloqueadas. Caso existam reuniões futuras, você poderá mantê-las ou cancelá-las.
```

Warning behavior:

- show it as soon as `Inativa` is selected;
- keep it visible while `Inativa` remains selected;
- also show it when the edit form initially loads an already inactive room;
- hide it if the administrator changes the status back to `Ativa`;
- use a warning icon and a light amber background without relying only on color;
- announce it through `aria-live="polite"` when it appears;
- keep the form editable because the final decision is made only when saving.

#### Deactivation decision dialog

When the administrator selects `Inativa`, chooses `Salvar`, and the room has one or more future active reservations, open a modal dialog before changing any data.

Suggested title and message:

```text
Desativar sala?

Há reuniões futuras agendadas nesta sala. O que você deseja fazer com elas?
```

When the reservation count is available, prefer a specific message:

```text
Há 3 reuniões futuras agendadas nesta sala. O que você deseja fazer com elas?
```

Below the message, display a radio group labeled:

```text
O que deseja fazer com as reuniões programadas?
```

The group contains two options:

| Radio option | Default | Result |
| --- | --- | --- |
| `Manter reuniões programadas` | Yes | Keeps all future reservations active after the room is deactivated |
| `Cancelar reuniões programadas` | No | Cancels all future active reservations after the room is deactivated |

`Manter reuniões programadas` must be selected by default because it is the least destructive option.

The footer contains only two buttons:

| Button | Behavior |
| --- | --- |
| `Cancelar` | Closes the dialog and returns to the form without saving |
| `Desativar` | Deactivates the room using the selected radio option |

Selecting a radio option must not immediately perform any operation. Data changes only after the administrator selects `Desativar`.

Dialog behavior:

- open it only when the status is changing from `Ativa` to `Inativa` and future active reservations exist;
- select `Manter reuniões programadas` by default every time the dialog opens;
- place initial focus on `Cancelar`, the safest button;
- allow `Escape` to perform the same behavior as `Cancelar`;
- keep focus trapped inside the dialog while it is open;
- return focus to `Salvar` when the dialog closes without completing the operation;
- support arrow-key navigation between the radio options;
- disable the radio group and both buttons after `Desativar` is selected;
- show a processing state until the request finishes;
- change the confirmation label to `Desativando...` during processing;
- use a neutral secondary treatment for `Cancelar`;
- use a destructive treatment for `Desativar`;
- if no future active reservation exists, save the inactive status without displaying the reservation-handling dialog;
- if the backend detects new reservations that were not present when the form loaded, return a decision-required response and open the dialog with the updated count.

Suggested success messages:

```text
Sala desativada. As reuniões programadas foram mantidas.
```

```text
Sala desativada. As reuniões futuras foram canceladas.
```

### Default status on creation

The backend must assign the active status when a room is created:

```text
status = active
```

The `POST /rooms` request should accept only the create-mode fields:

```text
name
capacity
```

The backend must not trust a client-provided status during creation. If an unexpected `status` value is submitted to `POST /rooms`, it should be ignored or rejected according to the request validation strategy, while the stored room remains active by default.

## Form actions

The actions appear at the bottom-right of the form card on desktop.

| Action | Behavior |
| --- | --- |
| `Cancelar` | Returns to `/rooms` without submitting |
| `Salvar` | Submits the form |

In edit mode, the primary action may remain `Salvar` or use `Salvar alterações`. The chosen label must be consistent across the application.

### Cancel behavior

- if no field has changed, navigate directly to `/rooms`;
- if the form contains unsaved changes, the application may show a confirmation dialog before leaving;
- canceling must never submit or mutate room data.

Suggested unsaved-changes message, if implemented:

```text
Descartar alterações?
As informações não salvas serão perdidas.
```

## Submission behavior

### Create mode

1. The administrator enters the room name and capacity.
2. The frontend submits `POST /rooms` through Inertia.
3. Laravel validates only the create-mode fields on the server.
4. The backend explicitly assigns the active status.
5. The room is created if validation succeeds.
6. The administrator is redirected to `/rooms`.
7. A success flash message is displayed.

Suggested message:

```text
Sala criada com sucesso.
```

### Edit mode

1. The backend provides the current room data to the Inertia page.
2. The administrator changes one or more values.
3. If the room is being deactivated and has future active reservations, the interface requests the reservation-handling decision.
4. The frontend submits `PUT` or `PATCH /rooms/{room}` with the selected reservation-handling option when applicable.
5. Laravel validates and updates the correct room.
6. If cancellation was selected, future active reservations are marked as canceled and their time slots are released.
7. The administrator is redirected to `/rooms`.
8. A success flash message is displayed.

Suggested message:

```text
Sala atualizada com sucesso.
```

## Validation errors

Errors returned by Laravel must appear directly below the corresponding fields.

Suggested messages:

```text
Informe o nome da sala.
A capacidade deve ser um número inteiro.
A capacidade deve ser de pelo menos 1 pessoa.
```

In edit mode, the backend may additionally return:

```text
Selecione o status da sala.
```

When validation fails:

- preserve all valid submitted values;
- keep the user on the same form mode;
- visually mark invalid fields;
- connect each error message to its field;
- move focus to the first invalid field or to an accessible error summary;
- do not create or partially update a room.

## Loading and processing state

While a submission is in progress:

- disable `Salvar` to prevent duplicate submissions;
- disable `Cancelar` if leaving would interrupt the active request;
- keep field values visible;
- change the primary label to `Salvando...`;
- show a loading indicator;
- prevent the form from being submitted a second time.

## Error states

### Unexpected submission failure

Display a general message above the form:

```text
Não foi possível salvar a sala. Tente novamente.
```

Keep the user's field values whenever safely possible.

### Room not found in edit mode

If the requested room does not exist, was deleted, or is not accessible, return an HTTP `404` response and render the application's not-found state. Do not render an empty creation form under an edit URL.

### Unauthorized session

If the session expires or the user is unauthenticated, redirect to `/login`. The backend must protect all form and submission routes independently of frontend checks.

## Inactive room rule

Only the edit form allows the status to be changed. Changing a room from `Ativa` to `Inativa` must prevent new reservations for that room.

Existing reservations must never be silently deleted or canceled. When future active reservations exist, the administrator must explicitly choose whether to keep or cancel them.

The inline warning must communicate that a decision may be required:

```text
Ao desativar esta sala, novas reservas serão bloqueadas. Caso existam reuniões futuras, você poderá mantê-las ou cancelá-las.
```

### Keep future reservations

When `Manter reuniões programadas` is selected and the administrator confirms `Desativar`:

- mark the room as inactive;
- preserve all existing reservation records and statuses;
- prevent any new reservation from being created for the room;
- continue displaying the preserved meetings in the reservations list.

### Cancel future reservations

When `Cancelar reuniões programadas` is selected and the administrator confirms `Desativar`:

- mark the room as inactive;
- cancel only active reservations whose start time is still in the future;
- preserve canceled reservation records in the database (`cancelled_at`); they disappear from the reservations list;
- release the canceled time intervals;
- do not alter completed, already canceled, or currently in-progress meetings.

### Consistency requirement

The room deactivation and the selected reservation operation must be completed atomically in a database transaction. The backend must lock or otherwise protect the relevant room and reservation records so that a concurrent request cannot create a new active reservation while the room is being deactivated.

If any part of the operation fails, neither the room status nor the reservations may be partially changed.

## Responsiveness

### Desktop and landscape tablet

- display the expanded sidebar;
- keep the top bar above the content;
- display the form card with a comfortable maximum width;
- keep labels above their fields;
- align `Cancelar` and `Salvar` to the bottom-right.

### Portrait tablet

- allow the sidebar to collapse into an icon rail or drawer;
- keep the form card centered;
- retain comfortable spacing and full-width fields.

### Mobile

- move the sidebar into an accessible navigation drawer;
- display the form card as a full-width content surface;
- make fields and buttons fill the available width;
- stack the actions when horizontal space is insufficient;
- keep the primary action visually prominent;
- ensure numeric controls remain easy to use with touch;
- prevent horizontal page scrolling.

## Accessibility

- associate every label with its input;
- mark required fields visually and programmatically;
- provide visible keyboard focus for fields, selects, buttons, and navigation;
- use `inputmode="numeric"` where appropriate without weakening server validation;
- connect errors using `aria-invalid` and `aria-describedby`;
- announce general errors and success messages through an accessible live region;
- maintain logical keyboard and screen-reader order;
- ensure the custom select, if used, supports keyboard operation;
- expose the deactivation dialog with an accessible title and description;
- trap focus inside the deactivation dialog and return it to the triggering control when dismissed;
- do not rely only on color to communicate errors or status.

## Visual guidelines

| Element | Guideline |
| --- | --- |
| Application background | Very light blue-gray |
| Sidebar | Dark navy with white and muted-blue text |
| Active navigation item | Lighter blue overlay with white text |
| Form card | White, rounded corners, light border, and subtle shadow |
| Labels | Dark navy text with red required indicators |
| Fields | White background, light-gray border, and rounded corners |
| Primary button | Bright blue with white text |
| Secondary button | White or light background with a subtle border |
| Error text | Red with sufficient contrast |
| Deactivation warning | Light amber background, warning icon, and dark readable text |
| Radio options | Clear labels with explanatory text and a visible selected state |
| `Cancelar` dialog button | Neutral secondary treatment |
| `Desativar` dialog button | Destructive treatment with clear text |

## Required states

- empty create form;
- populated edit form;
- focused text and number fields in create mode;
- focused text, number, and select fields in edit mode;
- active and inactive status selections in edit mode;
- deactivation warning visible after selecting `Inativa`;
- deactivation decision dialog with future reservations;
- keep-meetings radio option selected by default;
- cancel-meetings radio option selected;
- deactivation with future reservations preserved;
- deactivation with future reservations canceled;
- deactivation processing failure with no partial changes;
- one or more validation errors;
- submission in progress;
- successful creation;
- successful update;
- unexpected submission failure;
- room not found in edit mode;
- expired or unauthenticated session.

## Acceptance criteria

- [ ] Only authenticated administrators can access the create and edit routes.
- [ ] Create and edit modes reuse the same form component.
- [ ] Create mode displays only name and capacity.
- [ ] Create mode does not display or submit a status field.
- [ ] Every newly created room is assigned `Ativa` by the backend.
- [ ] `POST /rooms` does not trust a client-provided status.
- [ ] Edit mode loads the selected room's current name, capacity, and status.
- [ ] Edit mode allows the administrator to activate or deactivate the room.
- [ ] Selecting `Inativa` displays the deactivation warning before the form is saved.
- [ ] The warning explains that new reservations will be blocked and future meetings may be kept or canceled.
- [ ] Changing the status back to `Ativa` hides the warning.
- [ ] Saving an inactive status opens the reservation-handling dialog when future active reservations exist.
- [ ] `Manter reuniões programadas` is selected by default whenever the dialog opens.
- [ ] Selecting a radio option does not change data before confirmation.
- [ ] Confirming `Desativar` with `Manter reuniões programadas` selected preserves existing reservation statuses.
- [ ] Confirming `Desativar` with `Cancelar reuniões programadas` selected cancels only future active reservations.
- [ ] `Cancelar` closes the dialog without saving or changing data.
- [ ] Canceling future reservations preserves their records and releases their time slots.
- [ ] Completed, already canceled, and in-progress meetings are not changed by deactivation.
- [ ] Room deactivation and reservation handling execute atomically without partial updates.
- [ ] Name and capacity are required in both modes; status is additionally required in edit mode.
- [ ] Capacity accepts integers greater than or equal to `1` only.
- [ ] Laravel performs all authoritative validation on the server.
- [ ] Validation errors appear next to their corresponding fields.
- [ ] Invalid submissions do not create or partially update a room.
- [ ] `Cancelar` returns to `/rooms` without mutating data.
- [ ] `Salvar` cannot submit the form more than once while processing.
- [ ] Successful creation redirects to `/rooms` and displays a success message.
- [ ] Successful editing redirects to `/rooms` and displays a success message.
- [ ] An inactive room cannot receive new reservations.
- [ ] Existing reservations are never silently removed or canceled when a room becomes inactive.
- [ ] The screen works correctly on desktop, tablet, and mobile.
- [ ] The layout follows `.local/image/screen-room-form.png` as its visual reference.
