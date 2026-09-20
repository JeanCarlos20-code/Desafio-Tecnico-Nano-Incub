# Edit Reservation Screen

## Overview

This screen allows an authenticated administrator to change only the title and responsible person of an **active** reservation. Date, time, room, and participants stay visible and locked.

- **Page route:** `GET /reservations/{reservation}/edit`
- **Submission route:** `PUT /reservations/{reservation}`
- **Access:** authenticated administrators only
- **Success destination:** `/reservations`
- **Expected technologies:** React, Inertia.js 2, Tailwind CSS, Laravel, and MySQL 8

Missing or cancelled reservations (`cancelled_at` not null) respond 404. Guests are redirected to login.

## Goal

Fix a typo in `title` or `responsible` without reopening occupancy (RF13–RF18, RNF09).

This screen does **not** change:

- `starts_at`
- `ends_at`
- `room_id`
- `participants`
- `cancelled_at`

Room capacity reduction still cannot lower `participants` here. That path remains cancel, then create again if needed.

## Shared administrative layout

Same authenticated layout as create reservation and the reservations list. `Reservas` is the active sidebar item.

## Main content

```text
Editar reserva
Altere somente o título e o responsável. Data, horário, sala e participantes permanecem bloqueados.
```

The form reuses the create-card visual language. Occupancy widgets are disabled and are not included in the PUT body.

## Form fields

| Field | Input | Editable | Required |
| --- | --- | --- | --- |
| `Sala` | Disabled text (room name) | No | Display only |
| `Responsável` | Text | Yes | Yes |
| `Título / finalidade` | Text | Yes | Yes |
| `Data` | Disabled date (`Y-m-d`) | No | Display only |
| `Horário de início` | Disabled time (`H:i`) | No | Display only |
| `Horário de término` | Disabled time (`H:i`) | No | Display only |
| `Participantes` | Disabled number | No | Display only |

Editable labels keep the red asterisk. Occupancy labels do not.

## Submission

The page sends only:

```text
title
responsible
```

Laravel `UpdateReservationRequest` requires those two fields (same Portuguese required messages as store) and rejects `starts_at`, `ends_at`, `room_id`, `participants`, and `cancelled_at` with `prohibited`.

Success flash:

```text
Reserva atualizada com sucesso.
```

Redirect: `GET /reservations`.

## Out of scope on this screen

- Overlap, duration, past-start, capacity, or inactive-room checks
- Reservation or room lock
- Un-cancelling
- Lowering `participants` to satisfy a room capacity drop
