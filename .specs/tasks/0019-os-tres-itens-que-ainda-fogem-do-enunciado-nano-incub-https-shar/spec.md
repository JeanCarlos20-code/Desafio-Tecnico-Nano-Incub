# Specification

## Context

The Nano Incub challenge still disagrees with three delivered slices: list tables hide IDs and label situação as Status; README setup exists but section 05 (decisions, leftovers, AI declaration) does not; `DatabaseSeeder` seeds rooms/reservations but not administrators (RF19). The user authorized changing ADRs and create-migrations so rooms and reservations use incrementing bigint ids. UUID v7 remains only on `users`.

## Problem

Administrators cannot read the challenge list columns (especially ID). A reviewer following only the README cannot see why overlap is checked in the app **and** locked in MySQL, what was cut, or which AI tools were used. `php artisan db:seed` does not itself guarantee an administrator if the admin migration is absent.

## Goal

- Rooms and reservations persist incrementing bigint ids; lists show those ids; table situação is labeled `Situação`.
- README in Portuguese covers challenge section 05 without secrets.
- `DatabaseSeeder` idempotently ensures the three known administrators; migrate+seed leaves exactly three users.

## Problem Statement

The panel already implements rooms, reservations, overlap, and migrate-time demo data, but three challenge items remain wrong: hidden list IDs plus UUID identity on rooms/reservations, an incomplete README section 05, and a seeder that does not create administrators. This spec closes those items without reopening ADR-001 scope or user UUID identity.

## User Stories

### P1: List IDs and incrementing identity ⭐ MVP

**User Story**: As an administrator, I want room and reservation lists to show the persisted ID (and Situação) so the catalog matches the challenge columns.

**Why P1**: RF03/RF08 and the challenge tables require ID; current screens hide it.

**Acceptance Criteria**:

1. WHEN a room is persisted THEN the system SHALL store `rooms.id` as an unsigned bigint autoincrement (`$table->id()`) and SHALL NOT generate a UUID for that row.
2. WHEN a reservation is persisted THEN the system SHALL store `reservations.id` as an unsigned bigint autoincrement and SHALL store `reservations.room_id` as a `foreignId` to `rooms.id`.
3. WHEN an authenticated administrator opens `GET /rooms` THEN the system SHALL render a table columnheader `ID` and SHALL display each room's persisted id.
4. WHEN an authenticated administrator opens `GET /reservations` THEN the system SHALL render a table columnheader `ID` and SHALL display each reservation's persisted id.
5. WHEN the rooms or reservations table headers are rendered THEN the system SHALL label the situation column `Situação`.
6. The system SHALL keep `users.id` as UUID v7 (`HasUuids`) and SHALL NOT rewrite the ADR-002 decision body.

**Independent Test**: Migrate, create a room and a reservation, assert integer PKs/FK, and assert both Index pages show `ID` plus the persisted values.

### P1: README section 05 ⭐ MVP

**User Story**: As a reviewer, I want the README to explain decisions, leftovers, and AI use so I can run and evaluate the delivery from that file alone (RNF12 + challenge §05).

**Why P1**: Section 05 is an explicit delivery item; setup text already exists.

**Acceptance Criteria**:

1. WHEN a reviewer reads `README.md` THEN the system SHALL include a Portuguese section that states the overlap trade-off: Application rules RF13–RF18 plus persistence `lockForUpdate` on the room (RNF09 / ADR-006), and why those two together — not application-only and not a unique index alone.
2. WHEN a reviewer reads `README.md` THEN the system SHALL list what was left out and what would be done with more time, matching ADR-001 cuts (public signup, reservation edit, calendar, email, extra roles).
3. WHEN a reviewer reads `README.md` THEN the system SHALL declare that Cursor (agents) was used to plan, implement, and test, and that the author is responsible for the code.
4. IF `README.md` is updated THEN the system SHALL NOT add secrets, real `.env` values, or a `CONTRIBUTING.md`.

**Independent Test**: Read README and confirm the three narratives exist in Portuguese and name no extra AI tools.

### P1: Seeder creates administrators ⭐ MVP

**User Story**: As an administrator following the README, I want `db:seed` to guarantee the known login accounts so RF19 is literally true even if the admin migration did not run.

**Why P1**: Challenge §05 item 3 and RF19 require a seeder that creates at least one administrator.

**Acceptance Criteria**:

1. WHEN `DatabaseSeeder` runs THEN the system SHALL `firstOrCreate` Gertrudes (`teste@mail.com`), Marcelo (`teste2@mail.com`), and Emerson (`teste3@mail.com`) with password `Senha123` hashed by Laravel Argon.
2. WHEN `DatabaseSeeder` runs after the default-administrators migration THEN the system SHALL persist exactly three `users` rows and SHALL NOT insert `test@example.com` or a fourth administrator.
3. WHEN the three known administrator emails are missing and `DatabaseSeeder` runs THEN the system SHALL create those three users and no others.
4. The system SHALL keep room and reservation seeders idempotent (`firstOrCreate`) so migrate+seed still yields three demo rooms and three demo reservations.

**Independent Test**: RefreshDatabase + seed → 3 users; delete the three emails + seed → 3 users again; never `test@example.com`.

## Acceptance Criteria

Traceable copies of the story criteria (same outcomes):

1. WHEN a room is persisted THEN the system SHALL store `rooms.id` as an unsigned bigint autoincrement (`$table->id()`) and SHALL NOT generate a UUID for that row.
2. WHEN a reservation is persisted THEN the system SHALL store `reservations.id` as an unsigned bigint autoincrement and SHALL store `reservations.room_id` as a `foreignId` to `rooms.id`.
3. WHEN an authenticated administrator opens `GET /rooms` THEN the system SHALL render a table columnheader `ID` and SHALL display each room's persisted id.
4. WHEN an authenticated administrator opens `GET /reservations` THEN the system SHALL render a table columnheader `ID` and SHALL display each reservation's persisted id.
5. WHEN the rooms or reservations table headers are rendered THEN the system SHALL label the situation column `Situação`.
6. The system SHALL keep `users.id` as UUID v7 (`HasUuids`) and SHALL NOT rewrite the ADR-002 decision body.
7. WHEN a reviewer reads `README.md` THEN the system SHALL include a Portuguese section that states the overlap trade-off: Application rules RF13–RF18 plus persistence `lockForUpdate` on the room (RNF09 / ADR-006), and why those two together — not application-only and not a unique index alone.
8. WHEN a reviewer reads `README.md` THEN the system SHALL list what was left out and what would be done with more time, matching ADR-001 cuts (public signup, reservation edit, calendar, email, extra roles).
9. WHEN a reviewer reads `README.md` THEN the system SHALL declare that Cursor (agents) was used to plan, implement, and test, and that the author is responsible for the code.
10. IF `README.md` is updated THEN the system SHALL NOT add secrets, real `.env` values, or a `CONTRIBUTING.md`.
11. WHEN `DatabaseSeeder` runs THEN the system SHALL `firstOrCreate` Gertrudes (`teste@mail.com`), Marcelo (`teste2@mail.com`), and Emerson (`teste3@mail.com`) with password `Senha123` hashed by Laravel Argon.
12. WHEN `DatabaseSeeder` runs after the default-administrators migration THEN the system SHALL persist exactly three `users` rows and SHALL NOT insert `test@example.com` or a fourth administrator.
13. WHEN the three known administrator emails are missing and `DatabaseSeeder` runs THEN the system SHALL create those three users and no others.
14. The system SHALL keep room and reservation seeders idempotent (`firstOrCreate`) so migrate+seed still yields three demo rooms and three demo reservations.

## Edge Cases

- IF `StoreReservationRequest` or `IndexReservationRequest` receives a non-integer `room_id` THEN the system SHALL reject it with `Selecione uma sala válida.`
- IF a room or reservation route parameter is unknown or malformed THEN the system SHALL return HTTP 404 and SHALL leave other rows unchanged.
- IF `UserSeeder` runs when the three emails already exist THEN the system SHALL NOT update passwords or insert extra users.
- IF the demo catalog migration runs THEN the system SHALL omit explicit room/reservation ids and let MySQL assign them.
- IF ADR-004 or ADR-005 are touched THEN the system SHALL change only Status/supersede of the identity slice.

## Out of Scope

| Feature | Reason |
| ------- | ------ |
| Changing `users.id` away from UUID v7 | User decision; ADR-002 identity stays |
| Rewriting ADR-001 / ADR-002 / ADR-004 / ADR-005 decision bodies | ADRs are immutable; new ADR-008 supersedes the identity slice |
| Unique index or DB exclusion constraint for overlap | ADR-006 lock + Application rules stay; README explains why |
| Playwright / E2E bootstrap | No Playwright project; packet says N/A |
| Reservation edit, calendar, email, public signup, extra roles | ADR-001 |
| `CONTRIBUTING.md` | Packet forbids it |
| Renaming room filter or room form `Status` | Only list table columns must match the challenge |
| Changing Domain `id` / `roomId` from `string` to `int` | Opaque string + Infra cast; avoids rewriting Application unit fakes |

## Assumptions & Open Questions

| Assumption / decision | Chosen default | Rationale | Confirmed? |
| --------------------- | -------------- | --------- | ---------- |
| Edit create migrations instead of a convert migration | Edit `2026_09_18_120000`, `2026_09_18_180000`, `2026_09_20_000000` | No production data; user authorized | y |
| Domain identifiers | Keep `string`; repos cast `(string) $model->getKey()` | Packet asked to update Domain only if the string type implied UUID; Application units stay isolated | n |
| List situation label | Table header `Situação`; filter/form stay `Status` | Cheap alignment with the challenge column name | y |
| UserSeeder password write | Pass plaintext `Senha123` into Eloquent (`hashed` cast) | Avoids double-hash vs `Hash::make` + cast | n |
| Overlap persistence | Do not add a unique index | User asked README to explain the existing app + lock pair | y |
| E2E | `tests_not_applicable.e2e` | No Playwright; do not bootstrap | y |

**Open questions:** none — all resolved or logged above.

## Considered Approaches

1. **New ADR-008 + edit create migrations + incrementing ids + ID/Situação columns + UserSeeder + README §05** — matches the user packet; smallest honest identity change; no production migration.
2. **Keep UUID v7 on rooms/reservations and only unhide ID in the UI** — would show UUIDs; user explicitly rejected this and authorized bigint.
3. **Add an alter migration from uuid to bigint** — correct if production existed; user said there is none and to prefer editing create migrations.
4. **Unique index (or exclusion constraint) instead of lock + app rules** — cannot express consecutive-touch, cancelled reuse, duration, capacity, or inactive-room rules; rejected.
5. **Change Domain ids to `int`** — type-accurate but rewrites every use-case fake; rejected for this task.

## Selected Approach

Approach 1. Write ADR-008 (MADR, Portuguese, 2026-09-20). On ADR-004 and ADR-005, update only Status/supersede for the UUID identity slice. Switch rooms/reservations to `$table->id()` / `foreignId`, drop `HasUuids` on those models, stop generating UUIDs in the demo migration, and change reservation `room_id` validation from `uuid` to `integer`. Show persisted ids on both lists and rename the situation table header to `Situação`. Add `UserSeeder` (`firstOrCreate` by email) and the README §05 narrative. Keep user UUID, ADR-006 locking, and Application overlap rules.

## Requirement Traceability

| Requirement ID | Story | Phase | Status |
| -------------- | ----- | ----- | ------ |
| IDENT-01 | P1: List IDs and incrementing identity | Tasks | Complete |
| IDENT-02 | P1: List IDs and incrementing identity | Tasks | Complete |
| IDENT-03 | P1: List IDs and incrementing identity | Tasks | Complete |
| IDENT-04 | P1: List IDs and incrementing identity | Tasks | Complete |
| IDENT-05 | P1: List IDs and incrementing identity | Tasks | Complete |
| IDENT-06 | P1: List IDs and incrementing identity | Tasks | Complete |
| README-01 | P1: README section 05 | Tasks | Complete |
| README-02 | P1: README section 05 | Tasks | Complete |
| README-03 | P1: README section 05 | Tasks | Complete |
| README-04 | P1: README section 05 | Tasks | Complete |
| SEED-01 | P1: Seeder creates administrators | Tasks | Complete |
| SEED-02 | P1: Seeder creates administrators | Tasks | Complete |
| SEED-03 | P1: Seeder creates administrators | Tasks | Complete |
| SEED-04 | P1: Seeder creates administrators | Tasks | Complete |

**Coverage:** 14 total, 14 mapped to tasks, 0 unmapped.

## Success Criteria

- [x] Fresh migrate stores incrementing integer ids on rooms and reservations; users remain UUID v7
- [x] Both list pages show column `ID` and column `Situação` with the persisted id visible
- [x] README documents overlap (app + lock), leftovers, and Cursor AI use in Portuguese
- [x] migrate+seed yields exactly three administrators and never `test@example.com`
